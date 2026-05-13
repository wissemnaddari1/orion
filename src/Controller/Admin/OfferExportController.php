<?php

namespace App\Controller\Admin;

use App\Repository\OfferRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Dompdf\Dompdf;
use Dompdf\Options;

class OfferExportController extends AbstractController
{
    public function exportOffersPdf(OfferRepository $offerRepository): Response
    {
        // Increase memory limit and max execution time for PDF generation
        ini_set('memory_limit', '1024M');
        set_time_limit(300);

        // Capped at 500 to prevent memory exhaustion; use Paginator so LIMIT applies to entities not rows.
        $query = $offerRepository->createQueryBuilder('o')
            ->leftJoin('o.serviceRequest', 'sr')
            ->leftJoin('o.worker', 'w')
            ->addSelect('sr', 'w')
            ->orderBy('o.createdAt', 'DESC')
            ->setMaxResults(500)
            ->getQuery();
        $paginator = new Paginator($query, true);
        $offers = iterator_to_array($paginator);

        // Render HTML using Twig
        $html = $this->renderView('pdf/offers.html.twig', [
            'offers' => $offers,
        ]);

        // Configure Dompdf
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // Output PDF
        return new Response(
            $dompdf->output(),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="offers-list.pdf"',
            ]
        );
    }
}
