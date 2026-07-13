<?php

namespace App\Console\Commands;

use App\Models\LegalDocument;
use App\Models\Tenant;
use Illuminate\Console\Command;

class PublishLegalDocument extends Command
{
    protected $signature = 'legal:publish
        {type : privacy or member_terms}
        {version : Immutable document version}
        {title : Display title}
        {content-file : UTF-8 text file}
        {--tenant-code= : Omit for a global document}';

    protected $description = 'Publish an immutable versioned legal document';

    public function handle(): int
    {
        if (! in_array($this->argument('type'), ['privacy', 'member_terms'], true)) {
            $this->error('Type must be privacy or member_terms.');
            return self::FAILURE;
        }
        $path = realpath($this->argument('content-file'));
        if (! $path || ! is_file($path)) {
            $this->error('Content file was not found.');
            return self::FAILURE;
        }
        $content = trim((string) file_get_contents($path));
        if ($content === '') {
            $this->error('Content file is empty.');
            return self::FAILURE;
        }

        $tenant = null;
        if ($this->option('tenant-code')) {
            $tenant = Tenant::where('code', $this->option('tenant-code'))->first();
            if (! $tenant) {
                $this->error('Tenant was not found.');
                return self::FAILURE;
            }
        }
        $scopeKey = $tenant ? "tenant:{$tenant->id}" : 'global';
        $hash = hash('sha256', $content);
        $existing = LegalDocument::where([
            'scope_key' => $scopeKey,
            'type' => $this->argument('type'),
            'version' => $this->argument('version'),
        ])->first();
        if ($existing && $existing->content_hash !== $hash) {
            $this->error('That immutable version already exists with different content.');
            return self::FAILURE;
        }

        $document = LegalDocument::updateOrCreate(
            ['scope_key' => $scopeKey, 'type' => $this->argument('type'), 'version' => $this->argument('version')],
            [
                'tenant_id' => $tenant?->id,
                'title' => $this->argument('title'),
                'content' => $content,
                'content_hash' => $hash,
                'status' => 'published',
                'is_required' => true,
                'published_at' => now(),
            ],
        );
        $this->info("Published legal document #{$document->id} ({$scopeKey}).");
        return self::SUCCESS;
    }
}
