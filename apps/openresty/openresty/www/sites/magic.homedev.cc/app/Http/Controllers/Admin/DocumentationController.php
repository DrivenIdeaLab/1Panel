<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use League\CommonMark\CommonMarkConverter;
use Illuminate\View\View;
use Symfony\Component\Finder\Finder;

class DocumentationController extends Controller
{
    protected $docsPath;
    protected $converter;

    public function __construct()
    {
        $this->docsPath = base_path('docs');
        $this->converter = new CommonMarkConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    /**
     * Display documentation index with list of available docs
     */
    public function index(): View
    {
        $docs = $this->getAvailableDocumentation();

        return view('panel.admin.documentation.index', [
            'docs' => $docs,
            'totalDocs' => count($docs),
        ]);
    }

    /**
     * Display a specific documentation file
     */
    public function show(string $doc): View
    {
        $filePath = $this->getDocumentationPath($doc);

        if (!file_exists($filePath) || !$this->isValidDocFile($filePath)) {
            abort(404, 'Documentation not found');
        }

        $content = file_get_contents($filePath);
        $html = $this->converter->convert($content)->getContent();

        // Get all docs for sidebar navigation
        $allDocs = $this->getAvailableDocumentation();
        $currentDoc = $this->findDocBySlug($doc, $allDocs);

        return view('panel.admin.documentation.show', [
            'doc' => $currentDoc,
            'html' => $html,
            'allDocs' => $allDocs,
            'slug' => $doc,
        ]);
    }

    /**
     * Get list of available documentation files
     */
    private function getAvailableDocumentation(): array
    {
        if (!is_dir($this->docsPath)) {
            return [];
        }

        $docs = [];
        $finder = new Finder();
        $finder->files()->name('*.md')->in($this->docsPath)->depth('== 0')->sortByName();

        foreach ($finder as $file) {
            $slug = $file->getFilenameWithoutExtension();
            $title = $this->extractTitleFromMarkdown($file->getRealPath());

            $docs[] = [
                'slug' => $slug,
                'filename' => $file->getFilename(),
                'title' => $title,
                'description' => $this->extractDescription($file->getRealPath()),
            ];
        }

        return $docs;
    }

    /**
     * Get full path to documentation file
     */
    private function getDocumentationPath(string $doc): string
    {
        $slug = str_replace(['/', '\\', '.'], '', $doc);
        return $this->docsPath . '/' . $slug . '.md';
    }

    /**
     * Validate that the file is safe to read
     */
    private function isValidDocFile(string $filePath): bool
    {
        $realPath = realpath($filePath);
        $docsRealPath = realpath($this->docsPath);

        return $realPath && $docsRealPath && strpos($realPath, $docsRealPath) === 0;
    }

    /**
     * Extract title from markdown file (first H1)
     */
    private function extractTitleFromMarkdown(string $filePath): string
    {
        $content = file_get_contents($filePath);
        $lines = explode("\n", $content);

        foreach ($lines as $line) {
            if (preg_match('/^#+\s+(.+)$/m', $line, $matches)) {
                return trim($matches[1]);
            }
        }

        return basename($filePath, '.md');
    }

    /**
     * Extract first paragraph as description
     */
    private function extractDescription(string $filePath): string
    {
        $content = file_get_contents($filePath);
        $lines = explode("\n", $content);

        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line) && !preg_match('/^[#*-_]/', $line)) {
                return substr($line, 0, 100) . (strlen($line) > 100 ? '...' : '');
            }
        }

        return '';
    }

    /**
     * Find doc from collection by slug
     */
    private function findDocBySlug(string $slug, array $docs): ?array
    {
        foreach ($docs as $doc) {
            if ($doc['slug'] === $slug) {
                return $doc;
            }
        }

        return null;
    }
}
