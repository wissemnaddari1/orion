<?php

namespace App\Service;

use App\Entity\Contract;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

class ContractPdfService
{
    public function __construct(
        private readonly Environment $twig,
        private readonly string $projectDir,
    ) {}

    /**
     * Generate a PDF for the given contract and return the binary content.
     */
    public function generate(Contract $contract): string
    {
        $html = $this->twig->render('pdf/contract.html.twig', [
            'contract' => $contract,
        ]);

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'Helvetica');
        $options->setChroot($this->projectDir . '/public');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * Generate the PDF and save it to disk. Returns the relative path.
     */
    public function generateAndSave(Contract $contract): string
    {
        $pdfContent = $this->generate($contract);

        $dir = $this->projectDir . '/public/uploads/contracts/pdf';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = sprintf('contract_%d_%s.pdf', $contract->getId(), date('Ymd_His'));
        $path = $dir . '/' . $filename;
        file_put_contents($path, $pdfContent);

        // Store relative path from public/
        $relativePath = 'uploads/contracts/pdf/' . $filename;
        $contract->setSignedPdfPath($relativePath);

        return $relativePath;
    }
}
