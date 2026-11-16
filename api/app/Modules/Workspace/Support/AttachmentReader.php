<?php

namespace App\Modules\Workspace\Support;

use App\Modules\Workspace\Models\TaskAttachment;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use SimpleXMLElement;
use Smalot\PdfParser\Parser as PdfParser;
use Throwable;
use ZipArchive;

/**
 * Converte o conteúdo de um anexo em algo legível por um modelo de linguagem:
 * imagens (redimensionadas) ou texto extraído. Nunca executa o conteúdo.
 */
class AttachmentReader
{
    /** Lado máximo da imagem entregue ao modelo (px). */
    private const IMAGE_MAX_SIDE = 1568;

    /** Tamanho máximo da imagem entregue sem redimensionamento possível. */
    private const IMAGE_MAX_BYTES = 3_000_000;

    /** Limite de entradas lidas de arquivos compactados (docx/xlsx/pptx/zip). */
    private const MAX_ZIP_ENTRIES = 2000;

    /** Limite de bytes descompactados lidos de cada parte XML. */
    private const MAX_XML_BYTES = 20_000_000;

    private const TEXT_EXTENSIONS = ['txt', 'csv', 'md', 'json'];

    public function raw(TaskAttachment $attachment): string
    {
        $data = Storage::disk($attachment->disk)->get($attachment->path);

        if ($data === null) {
            throw new RuntimeException('Arquivo do anexo indisponível.');
        }

        return $data;
    }

    /**
     * @return array{data: string, mime_type: string}
     */
    public function image(TaskAttachment $attachment): array
    {
        $data = $this->raw($attachment);
        $mime = (string) $attachment->mime_type;

        if (function_exists('imagecreatefromstring') && ($resized = $this->resize($data, $mime)) !== null) {
            return $resized;
        }

        if (strlen($data) > self::IMAGE_MAX_BYTES) {
            throw new RuntimeException('Imagem grande demais para ser enviada ao modelo.');
        }

        return ['data' => $data, 'mime_type' => $mime];
    }

    /**
     * Texto extraído do anexo, ou null quando o formato não é suportado.
     */
    public function text(TaskAttachment $attachment): ?string
    {
        $extension = $this->extension($attachment);

        $text = match (true) {
            in_array($extension, self::TEXT_EXTENSIONS, true) || str_starts_with((string) $attachment->mime_type, 'text/') => $this->plain($this->raw($attachment)),
            $extension === 'pdf' => $this->pdf($this->raw($attachment)),
            $extension === 'docx' => $this->withZip($attachment, fn (ZipArchive $zip) => $this->docx($zip)),
            $extension === 'xlsx' => $this->withZip($attachment, fn (ZipArchive $zip) => $this->xlsx($zip)),
            $extension === 'pptx' => $this->withZip($attachment, fn (ZipArchive $zip) => $this->pptx($zip)),
            $extension === 'zip' => $this->withZip($attachment, fn (ZipArchive $zip) => $this->zipListing($zip)),
            default => null,
        };

        return $text === null ? null : trim($text);
    }

    private function extension(TaskAttachment $attachment): string
    {
        return strtolower(pathinfo((string) $attachment->original_name, PATHINFO_EXTENSION));
    }

    private function plain(string $data): string
    {
        if (str_starts_with($data, "\xEF\xBB\xBF")) {
            $data = substr($data, 3);
        }

        if (! mb_check_encoding($data, 'UTF-8')) {
            $data = mb_convert_encoding($data, 'UTF-8', 'Windows-1252');
        }

        return $data;
    }

    private function pdf(string $data): string
    {
        if (! class_exists(PdfParser::class)) {
            throw new RuntimeException('Leitura de PDF indisponível no servidor.');
        }

        try {
            $document = (new PdfParser)->parseContent($data);
        } catch (Throwable $e) {
            throw new RuntimeException('Não foi possível ler o PDF (arquivo protegido ou corrompido).', 0, $e);
        }

        $pages = [];

        foreach ($document->getPages() as $index => $page) {
            $pages[] = '--- Página '.($index + 1)." ---\n".trim($page->getText());
        }

        return implode("\n\n", $pages);
    }

    /**
     * @param  callable(ZipArchive): string  $callback
     */
    private function withZip(TaskAttachment $attachment, callable $callback): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('Leitura de arquivos compactados indisponível no servidor.');
        }

        $file = tempnam(sys_get_temp_dir(), 'nox-attachment-');

        try {
            file_put_contents($file, $this->raw($attachment));
            $zip = new ZipArchive;

            if ($zip->open($file, ZipArchive::RDONLY) !== true) {
                throw new RuntimeException('Arquivo compactado inválido ou corrompido.');
            }

            if ($zip->numFiles > self::MAX_ZIP_ENTRIES) {
                $zip->close();

                throw new RuntimeException('Arquivo com entradas demais para ser lido.');
            }

            try {
                return $callback($zip);
            } finally {
                $zip->close();
            }
        } finally {
            @unlink($file);
        }
    }

    private function entry(ZipArchive $zip, string $name): ?string
    {
        $stat = $zip->statName($name);

        if ($stat === false) {
            return null;
        }

        if ($stat['size'] > self::MAX_XML_BYTES) {
            throw new RuntimeException('Conteúdo descompactado grande demais para ser lido.');
        }

        $data = $zip->getFromName($name);

        return $data === false ? null : $data;
    }

    /**
     * Carrega XML sem resolver entidades externas nem acessar a rede.
     */
    private function xml(string $data): ?SimpleXMLElement
    {
        $previous = libxml_use_internal_errors(true);

        try {
            $xml = simplexml_load_string($data, options: LIBXML_NONET | LIBXML_COMPACT);

            return $xml === false ? null : $xml;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function docx(ZipArchive $zip): string
    {
        $xml = $this->entry($zip, 'word/document.xml')
            ?? throw new RuntimeException('Documento Word inválido.');

        $xml = preg_replace(['/<w:tab\/>/', '/<w:br[^>]*\/>/', '/<\/w:p>/', '/<\/w:tc>/'], ["\t", "\n", "\n", "\t"], $xml);

        return html_entity_decode(strip_tags((string) $xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function xlsx(ZipArchive $zip): string
    {
        $strings = [];

        if (($shared = $this->entry($zip, 'xl/sharedStrings.xml')) !== null && ($xml = $this->xml($shared)) !== null) {
            foreach ($xml->si as $item) {
                $strings[] = $this->xmlText($item);
            }
        }

        $names = [];

        if (($workbook = $this->entry($zip, 'xl/workbook.xml')) !== null && ($xml = $this->xml($workbook)) !== null) {
            foreach ($xml->sheets->sheet ?? [] as $sheet) {
                $names[] = (string) $sheet['name'];
            }
        }

        $sheets = [];

        foreach ($this->numberedEntries($zip, '#^xl/worksheets/sheet(\d+)\.xml$#') as $position => $name) {
            $xml = $this->xml((string) $this->entry($zip, $name));

            if ($xml === null) {
                continue;
            }

            $rows = [];

            foreach ($xml->sheetData->row ?? [] as $row) {
                $cells = [];

                foreach ($row->c as $cell) {
                    $cells[] = match ((string) $cell['t']) {
                        's' => $strings[(int) $cell->v] ?? '',
                        'inlineStr' => $this->xmlText($cell->is),
                        default => (string) $cell->v,
                    };
                }

                $rows[] = implode("\t", $cells);
            }

            $title = $names[$position] ?? 'Planilha '.($position + 1);
            $sheets[] = "--- {$title} ---\n".implode("\n", $rows);
        }

        return implode("\n\n", $sheets);
    }

    private function pptx(ZipArchive $zip): string
    {
        $slides = [];

        foreach ($this->numberedEntries($zip, '#^ppt/slides/slide(\d+)\.xml$#') as $position => $name) {
            preg_match_all('/<a:t>(.*?)<\/a:t>/s', (string) $this->entry($zip, $name), $matches);
            $text = html_entity_decode(implode("\n", $matches[1]), ENT_QUOTES | ENT_XML1, 'UTF-8');
            $slides[] = '--- Slide '.($position + 1)." ---\n".$text;
        }

        return implode("\n\n", $slides);
    }

    private function zipListing(ZipArchive $zip): string
    {
        $lines = ["Arquivo compactado com {$zip->numFiles} entrada(s):"];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);

            if ($stat !== false) {
                $lines[] = "- {$stat['name']} ({$stat['size']} bytes)";
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Entradas que casam com o padrão, ordenadas pelo número capturado.
     *
     * @return list<string>
     */
    private function numberedEntries(ZipArchive $zip, string $pattern): array
    {
        $entries = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);

            if (preg_match($pattern, $name, $match)) {
                $entries[(int) $match[1]] = $name;
            }
        }

        ksort($entries);

        return array_values($entries);
    }

    private function xmlText(?SimpleXMLElement $element): string
    {
        if ($element === null) {
            return '';
        }

        // Texto rico (<r><t>) ou simples (<t>), ignorando namespaces.
        $parts = $element->xpath('.//*[local-name()="t"]') ?: [];

        return implode('', array_map(fn ($node) => (string) $node, $parts));
    }

    /**
     * @return array{data: string, mime_type: string}|null
     */
    private function resize(string $data, string $mime): ?array
    {
        $info = @getimagesizefromstring($data);

        if ($info === false) {
            return null;
        }

        [$width, $height] = $info;
        $scale = min(1, self::IMAGE_MAX_SIDE / max($width, $height, 1));

        if ($scale === 1 && strlen($data) <= self::IMAGE_MAX_BYTES) {
            return ['data' => $data, 'mime_type' => $mime];
        }

        $source = @imagecreatefromstring($data);

        if ($source === false) {
            return null;
        }

        $target = imagescale($source, max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)));
        imagedestroy($source);

        if ($target === false) {
            return null;
        }

        // Fundo branco para imagens com transparência convertidas em JPEG.
        $canvas = imagecreatetruecolor(imagesx($target), imagesy($target));
        imagefill($canvas, 0, 0, (int) imagecolorallocate($canvas, 255, 255, 255));
        imagecopy($canvas, $target, 0, 0, 0, 0, imagesx($target), imagesy($target));
        imagedestroy($target);

        ob_start();
        imagejpeg($canvas, null, 85);
        $output = (string) ob_get_clean();
        imagedestroy($canvas);

        return ['data' => $output, 'mime_type' => 'image/jpeg'];
    }
}
