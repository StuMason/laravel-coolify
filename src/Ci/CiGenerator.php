<?php

declare(strict_types=1);

namespace Stumason\Coolify\Ci;

use Illuminate\Support\Facades\File;

/**
 * Generates GitHub Actions workflow files for automatic deployments to Coolify.
 *
 * This class creates a workflow that triggers on push to a specified branch
 * and calls the Coolify API to deploy the application.
 */
class CiGenerator
{
    protected const WORKFLOW_FILENAME = 'coolify-deploy.yml';

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
        env:
          COOLIFY_URL: \${{ secrets.COOLIFY_URL }}
          COOLIFY_TOKEN: \${{ secrets.COOLIFY_TOKEN }}
          COOLIFY_APP_UUID: \${{ secrets.COOLIFY_APPLICATION_UUID }}
        run: |
          response=\$(curl -s -w "\\n%{http_code}" \\
            -X POST "\${COOLIFY_URL}/api/v1/deploy" \\
            -H "Authorization: Bearer \${COOLIFY_TOKEN}" \\
            -H "Content-Type: application/json" \\
            -d "{\\"uuid\\": \\"\${COOLIFY_APP_UUID}\\"}")

          http_code=\$(echo "\$response" | tail -n1)
          body=\$(echo "\$response" | sed '\$d')

          if [ "\$http_code" -ne 200 ] && [ "\$http_code" -ne 201 ]; then
            echo "Deployment failed with HTTP \$http_code"
            echo "\$body"
            exit 1
          fi

          echo "Deployment triggered successfully"
          echo "\$body"
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

        $filePath = $workflowDir.'/'.self::WORKFLOW_FILENAME;
        File::put($filePath, $this->generate());

        return $filePath;
    }

    /**
     * Check if GitHub Actions workflow already exists.
     */
    public function exists(?string $basePath = null): bool
    {
        $basePath = $basePath ?? base_path();

        return File::exists($basePath.'/.github/workflows/'.self::WORKFLOW_FILENAME);
    }
}
