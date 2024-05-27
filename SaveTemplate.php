<?php
/**
 * Create an instance of the Custom Template Engine class,
 * and save template.
 */
$customTemplateEngine = new \BCCHR\CustomTemplateEngine\CustomTemplateEngine();
$customTemplateEngine->setPaths();
$result = $customTemplateEngine->saveTemplate();
print json_encode($result);
