<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Contract;
use App\Entity\Milestone;
use App\Entity\Offer;
use Doctrine\ORM\EntityManagerInterface;

final class ContractFromOfferService
{
	public function __construct(
		private EntityManagerInterface $entityManager,
		private ContractGeneratorClient $contractGeneratorClient,
	)
	{
	}

	public function createFromAcceptedOffer(Offer $offer): Contract
	{
		$serviceRequest = $offer->getServiceRequest();
		$client = $offer->getClient() ?? $serviceRequest?->getClient();
		$worker = $offer->getWorker();

		if ($serviceRequest === null || $client === null || $worker === null) {
			throw new \RuntimeException('Cannot create contract: offer is missing service request, client, or worker.');
		}

		$title = $this->buildTitle($serviceRequest->getTitle() ?? 'Service Request #' . (string) $serviceRequest->getId());
		$existing = $this->entityManager->getRepository(Contract::class)
			->createQueryBuilder('c')
			->andWhere('c.client = :client')
			->andWhere('c.worker = :worker')
			->andWhere('c.title = :title')
			->andWhere('c.status IN (:statuses)')
			->setParameter('client', $client)
			->setParameter('worker', $worker)
			->setParameter('title', $title)
			->setParameter('statuses', [
				Contract::STATUS_PENDING_SIGN,
				Contract::STATUS_ACTIVE,
				Contract::STATUS_IN_PROGRESS,
			])
			->setMaxResults(1)
			->getQuery()
			->getOneOrNullResult();
		if ($existing instanceof Contract) {
			return $existing;
		}

		$contract = new Contract();
		$contract->setClient($client);
		$contract->setWorker($worker);
		$contract->setTitle($title);

		$generated = $this->contractGeneratorClient->generateFromOffer($offer);
		$scope = $generated['generatedContract'] ?? null;
		if ($scope === null || trim($scope) === '') {
			$scope = $this->buildFallbackContractText($offer);
		}
		$contract->setScope($scope);
		$contract->setRiskScore($generated['riskScore'] ?? null);
		$contract->setRiskLevel($generated['riskLevel'] ?? 'MEDIUM');
		$contract->setAgreedPrice($offer->getPrice());
		$contract->setCurrency('USD');

		$start = new \DateTimeImmutable('today');
		$duration = $serviceRequest->getDuration();
		$days = ($duration !== null && $duration > 0) ? $duration : 30;
		$end = $start->modify('+' . $days . ' days');

		$contract->setStartDate(new \DateTime($start->format('Y-m-d')));
		$contract->setEndDate(new \DateTime($end->format('Y-m-d')));
		$contract->setStatus(Contract::STATUS_PENDING_SIGN);

		$this->createMilestonesFromOffer($contract, $offer, $start, $end);

		$this->entityManager->persist($contract);

		return $contract;
	}

	private function buildTitle(string $serviceTitle): string
	{
		return 'Contract - ' . trim($serviceTitle);
	}

	private function createMilestonesFromOffer(Contract $contract, Offer $offer, \DateTimeImmutable $start, \DateTimeImmutable $end): void
	{
		$milestoneTitles = $this->extractMilestoneTitles($offer);
		$milestoneCount = max(1, count($milestoneTitles));

		$totalCents = (int) round(((float) $offer->getPrice()) * 100);
		$baseCents = (int) floor($totalCents / $milestoneCount);
		$remainingCents = $totalCents - ($baseCents * $milestoneCount);

		$startTs = $start->getTimestamp();
		$endTs = $end->getTimestamp();
		$durationTs = max(0, $endTs - $startTs);

		for ($index = 1; $index <= $milestoneCount; ++$index) {
			$milestone = new Milestone();
			$milestone->setContract($contract);
			$milestone->setOrderIndex($index);
			$milestone->setStatus(Milestone::STATUS_PENDING);

			$title = $milestoneTitles[$index - 1] ?? null;
			if ($title === null || trim($title) === '') {
				$title = sprintf('Milestone %d of %d', $index, $milestoneCount);
			}
			$milestone->setTitle($title);

			$ratio = $index / $milestoneCount;
			$dueTs = (int) round($startTs + ($durationTs * $ratio));
			$dueDate = (new \DateTimeImmutable())->setTimestamp($dueTs);
			$milestone->setDueDate(new \DateTime($dueDate->format('Y-m-d')));

			$cents = $baseCents;
			if ($index === $milestoneCount) {
				$cents += $remainingCents;
			}
			$milestone->setAmount(number_format($cents / 100, 2, '.', ''));

			$contract->addMilestone($milestone);
		}
	}

	/**
	 * @return list<string>
	 */
	private function extractMilestoneTitles(Offer $offer): array
	{
		$deliverables = trim((string) ($offer->getDeliverables() ?? ''));
		if ($deliverables === '') {
			return ['Project Delivery'];
		}

		$parts = preg_split('/\r\n|\r|\n|;|\|/', $deliverables) ?: [];
		$titles = array_values(array_filter(array_map(static fn ($value) => trim((string) $value), $parts), static fn ($value) => $value !== ''));

		if ($titles === []) {
			return ['Project Delivery'];
		}

		return array_slice($titles, 0, 6);
	}

	private function buildFallbackContractText(Offer $offer): string
	{
		$serviceRequest = $offer->getServiceRequest();
		$title = trim((string) ($serviceRequest?->getTitle() ?? 'Service Contract'));
		$description = trim((string) ($serviceRequest?->getDescription() ?? ''));
		$deliverables = trim((string) ($offer->getDeliverables() ?? ''));
		$acceptance = trim((string) ($offer->getAcceptanceCriteria() ?? ''));
		$price = number_format((float) $offer->getPrice(), 2, '.', '');
		$days = (int) ($offer->getEstimatedTimeDays() > 0 ? $offer->getEstimatedTimeDays() : ($serviceRequest?->getDuration() ?? 30));

		$deliverablesText = $deliverables !== '' ? $deliverables : 'Deliverables will follow the approved offer and project scope.';
		$acceptanceText = $acceptance !== '' ? $acceptance : 'Work is accepted when deliverables match the agreed scope and quality standards.';
		$descriptionText = $description !== '' ? $description : 'The freelancer will complete the requested service as described in the accepted offer.';

		return <<<TEXT
Contract Title: {$title}

1) Scope of Work
{$descriptionText}

2) Deliverables
{$deliverablesText}

3) Payment Terms
Total agreed amount: {$price} USD.
Payment follows the contract milestones and is released upon acceptance of each completed milestone.

4) Timeline
Estimated delivery timeline: {$days} days from contract start date, unless both parties agree in writing to a revised schedule.

5) Acceptance Criteria
{$acceptanceText}

6) Confidentiality
Both parties agree to keep project information and exchanged materials confidential unless disclosure is required by law.

7) Termination
Either party may terminate for material breach if the breach is not cured after written notice and a reasonable cure period.

8) Dispute Resolution
Parties agree to attempt amicable resolution first. If unresolved, disputes are handled under applicable law and competent jurisdiction.
TEXT;
	}
}
