<?php

declare(strict_types=1);

namespace Stumason\Coolify\Ci;

use Illuminate\Support\Facades\File;

class CiGenerator
{
    protected string $branch = 'main';

    protected bool $manualTrigger = true;

    /**
     * Set the branch to deploy from.
     */
    public function branch(string $branch): self
    {
        $this->branch = $branch;

        return $this;
    }

    /**
     * Enable or disable manual workflow dispatch.
     */
    public function manualTrigger(bool $enabled): self
    {
        $this->manualTrigger = $enabled;

        return $this;
    }

    /**
     * Generate GitHub Actions workflow file content.
     */
    public function generate(): string
    {
        $triggers = "push:\n    branches: [{$this->branch}]";

        if ($this->manualTrigger) {
            $triggers .= "\n  workflow_dispatch:";
        }

        return <<<YAML
name: Deploy to Coolify

on:
  {$triggers}

jobs:
  deploy:
    name: Deploy Application
    runs-on: ubuntu-latest

    steps:
      - name: Deploy to Coolify
        run: |
          curl -X POST "\${{ secrets.COOLIFY_URL }}/api/v1/deploy" \\
            -H "Authorization: Bearer \${{ secrets.COOLIFY_TOKEN }}" \\
            -H "Content-Type: application/json" \\
            -d '{"uuid": "\${{ secrets.COOLIFY_APPLICATION_UUID }}"}'
YAML;
    }

    /**
     * Write GitHub Actions workflow to disk.
     */
    public function write(?string $basePath = null): string
    {
        $basePath = $basePath ?? base_path();
        $workflowDir = $basePath.'/.github/workflows';

        if (! File::isDirectory($workflowDir)) {
            File::makeDirectory($workflowDir, 0755, true);
        }

        $filePath = $workflowDir.'/coolify-deploy.yml';
        File::put($filePath, $this->generate());

        return $filePath;
    }

    /**
     * Check if GitHub Actions workflow already exists.
     */
    public function exists(?string $basePath = null): bool
    {
        $basePath = $basePath ?? base_path();

        return File::exists($basePath.'/.github/workflows/coolify-deploy.yml');
    }
}
