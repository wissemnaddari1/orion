<?php

namespace App\Controller\Admin;

use App\Repository\OfferRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class OfferBulkActionController extends AbstractController
{
    public function bulkAction(Request $request, OfferRepository $offerRepository, EntityManagerInterface $em): Response
    {
        $action = $request->request->get('bulk_action');
        $offerIds = $request->request->all('offer_ids');
        if (!$action || empty($offerIds)) {
            $this->addFlash('error', 'No offers selected or action missing.');
            return $this->redirectToRoute('admin_offers_index');
        }
        $offers = $offerRepository->findBy(['id' => $offerIds]);
        $count = 0;
        foreach ($offers as $offer) {
            switch ($action) {
                case 'accept':
                    $offer->setStatus('ACCEPTED');
                    $count++;
                    break;
                case 'reject':
                    $offer->setStatus('REJECTED');
                    $count++;
                    break;
                case 'delete':
                    $em->remove($offer);
                    $count++;
                    break;
            }
        }
        $em->flush();
        $this->addFlash('success', sprintf('%d offers processed for "%s".', $count, $action));
        return $this->redirectToRoute('admin_offers_index');
    }
}
