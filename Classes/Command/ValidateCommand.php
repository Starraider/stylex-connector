<?php

declare(strict_types=1);

namespace Vendor\StylexConnector\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Vendor\StylexConnector\Service\StylexManifestService;

final class ValidateCommand extends Command
{
    public function __construct(private readonly StylexManifestService $manifests)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Validate registered StyleX manifests and report their effective order.')
            ->addOption(
                'required-key',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Require a style key.'
            )
            ->addOption('report', null, InputOption::VALUE_NONE, 'Print manifest metadata and diagnostics as JSON.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Cache identity follows current manifest bytes; paired CSS is checked below.
        $report = $this->manifests->getReport();
        $errors = $report['diagnostics'];
        if ($report['registrations'] === []) {
            $errors[] = ['message' => 'No readable manifests registered.'];
        }
        foreach ($input->getOption('required-key') as $key) {
            if (!$this->manifests->hasStyle($key)) {
                $errors[] = ['message' => 'Missing required key: ' . $key];
            }
        }
        foreach ($report['registrations'] as $registration) {
            $artifacts = $registration['metadata']['artifacts'] ?? [];
            if (!is_array($artifacts)) {
                $errors[] = ['message' => 'Invalid artifacts list: ' . $registration['owner']];
                continue;
            }
            foreach ($artifacts as $artifact) {
                if (
                    !is_array($artifact) || !is_string($artifact['path'] ?? null)
                    || !is_string($artifact['sha256'] ?? null)
                ) {
                    $errors[] = ['message' => 'Invalid artifact declaration: ' . $registration['owner']];
                    continue;
                }
                $path = dirname($registration['path']) . '/' . $artifact['path'];
                if (!is_file($path) || !is_readable($path) || hash_file('sha256', $path) !== $artifact['sha256']) {
                    $errors[] = ['message' => 'Missing or mismatched paired artifact: ' . $path];
                }
            }
        }
        if ($input->getOption('report')) {
            $report['diagnostics'] = $errors;
            $json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            $output->writeln($json, OutputInterface::OUTPUT_RAW);
        } else {
            foreach ($errors as $error) {
                $output->writeln(json_encode($error), OutputInterface::OUTPUT_RAW);
            }
            $output->writeln(sprintf(
                '%d style keys in %d manifests.',
                $report['count'],
                count($report['registrations'])
            ));
        }
        return $errors === [] ? self::SUCCESS : self::FAILURE;
    }
}
