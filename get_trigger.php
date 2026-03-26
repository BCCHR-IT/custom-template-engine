<?php
declare(strict_types=1);

/**
 * @var \BCCHR\CustomTemplateEngine\CustomTemplateEngine $module
 */

// schema 1
header('Content-Type: application/json; charset=utf-8');

try {
    $pid = (int)($_GET['pid'] ?? 0);
    if ($pid <= 0) throw new Exception('Missing pid');

    $template = trim((string)($_GET['template'] ?? ''));
    if ($template === '') throw new Exception('Missing template');

    $cte = new \BCCHR\CustomTemplateEngine\CustomTemplateEngine();
    $cte->setPaths();

    $raw = $cte->getProjectSetting('cte_trigger_config', $pid);

    $cfg = ['schema' => 1, 'templates' => []];
    if (is_string($raw) && trim($raw) !== '') {
        $tmp = json_decode($raw, true);
        if (is_array($tmp) && (int)($tmp['schema'] ?? 0) === 1 && is_array($tmp['templates'] ?? null)) {
            $cfg = $tmp;
        }
    }

    $tCfg = $cfg['templates'][$template] ?? null;

    echo json_encode([
        'success' => true,
        'config'  => is_array($tCfg) ? $tCfg : null
    ]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}