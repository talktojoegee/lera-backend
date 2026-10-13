<?php

namespace App\Console\Commands;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Console\Command;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Html;
class GenerateEdoStateProposalCommand extends Command
{
    protected $signature = 'proposal:edo-state {--output=storage/app/proposals/edo-state}';

    protected $description = 'Generate Edo State Government LERA proposal (PDF and Word)';

    public function handle(): int
    {
        $outputDir = base_path($this->option('output'));
        if (! is_dir($outputDir) && ! mkdir($outputDir, 0755, true) && ! is_dir($outputDir)) {
            $this->error("Cannot create output directory: {$outputDir}");

            return self::FAILURE;
        }

        $html = view('proposals.edo-state')->render();

        $pdfPath = $outputDir.'/Edo_State_LERA_Proposal.pdf';
        Pdf::loadHTML($html)
            ->setPaper('A4', 'portrait')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => false,
                'defaultFont' => 'DejaVu Sans',
            ])
            ->save($pdfPath);

        $this->info("PDF saved: {$pdfPath}");

        $docxPath = $outputDir.'/Edo_State_LERA_Proposal.docx';
        $this->generateDocx($docxPath);

        $this->info("Word saved: {$docxPath}");
        $this->newLine();
        $this->line('Download paths (relative to project root):');
        $this->line('  '.str_replace(base_path().'/', '', $pdfPath));
        $this->line('  '.str_replace(base_path().'/', '', $docxPath));

        return self::SUCCESS;
    }

    protected function generateDocx(string $path): void
    {
        $phpWord = new PhpWord;
        $phpWord->setDefaultFontName('Calibri');
        $phpWord->setDefaultFontSize(11);

        $section = $phpWord->addSection([
            'marginTop' => 1200,
            'marginBottom' => 1200,
            'marginLeft' => 1200,
            'marginRight' => 1200,
        ]);

        $html = view('proposals.edo-state-docx')->render();
        Html::addHtml($section, $html, false, false);

        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($path);
    }
}
