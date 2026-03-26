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

    $enabled = !empty($_POST['enabled']) && $_POST['enabled'] === '1';
    $logic = trim((string)($_POST['logic'] ?? ''));
    $template = trim((string)($_POST['template'] ?? ''));
    $targetField = trim((string)($_POST['target_field'] ?? ''));
    $targetEventUnique = trim((string)($_POST['target_event_unique'] ?? ''));

    if ($template === '') throw new Exception('Template is required');

    if ($enabled) {
        if ($logic === '') throw new Exception('Logic is required');
        if ($targetField === '') throw new Exception('Target field is required');
    }

    $cte = new \BCCHR\CustomTemplateEngine\CustomTemplateEngine();
    $cte->setPaths();

    $raw = $cte->getProjectSetting('cte_trigger_config', $pid);

    // schema=1
    $cfg = ['schema' => 1, 'templates' => []];
    if (is_string($raw) && trim($raw) !== '') {
        $tmp = json_decode($raw, true);
        if (is_array($tmp) && (int)($tmp['schema'] ?? 0) === 1 && is_array($tmp['templates'] ?? null)) {
            $cfg = $tmp;
        }
    }

    $cfg['schema'] = 1;
    if (!is_array($cfg['templates'] ?? null)) $cfg['templates'] = [];

    $cfg['templates'][$template] = [
        'enabled' => $enabled,
        'logic' => $logic,
        'target_field' => $targetField,
        'target_event_unique' => $targetEventUnique,
    ];

    $cte->setProjectSetting('cte_trigger_config', json_encode($cfg), $pid);

    echo json_encode(['success' => true]);
    // echo json_encode(['success' => true, 'config' => $cfg['templates'][$template]]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}