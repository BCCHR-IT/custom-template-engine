<?php
declare(strict_types=1);

/** 
 * @var \BCCHR\CustomTemplateEngine\CustomTemplateEngine $module 
 */

// schema 1
header('Content-Type: application/json; charset=utf-8');

try {
    $pid = (int)($_GET['pid'] ?? 0);
    if ($pid <= 0) {
        throw new Exception('Missing pid');
    }

    $template = trim((string)($_POST['template'] ?? ''));
    if ($template === '') {
        throw new Exception('Template is required');
    }

    $cte = new \BCCHR\CustomTemplateEngine\CustomTemplateEngine();
    $cte->setPaths();

    $raw = $cte->getProjectSetting('cte_trigger_config', $pid);

    // Schema 1 only
    $cfg = ['schema' => 1, 'templates' => []];

    if (is_string($raw) && trim($raw) !== '') {
        $tmp = json_decode($raw, true);

        if (
            is_array($tmp)
            && (int)($tmp['schema'] ?? 0) === 1
            && is_array($tmp['templates'] ?? null)
        ) {
            $cfg = $tmp;
        }
    }

    if (!is_array($cfg['templates'] ?? null)) {
        $cfg['templates'] = [];
    }

    if (!array_key_exists($template, $cfg['templates'])) {
        echo json_encode([
            'success' => true,
            'deleted' => false,
            'message' => 'No trigger existed for this template.'
        ]);
        exit;
    }

    unset($cfg['templates'][$template]);

    if (empty($cfg['templates'])) {
        $cte->setProjectSetting('cte_trigger_config', '', $pid);
    } else {
        $cte->setProjectSetting('cte_trigger_config', json_encode($cfg), $pid);
    }

    echo json_encode([
        'success' => true,
        'deleted' => true,
    ]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}