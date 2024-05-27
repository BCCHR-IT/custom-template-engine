<?php
/**
 * Create instance of Custom Report Builder,
 * and display image gallery.
 */
$customReportBuilder = new \BCCHR\CustomTemplateEngine\CustomTemplateEngine();
$customReportBuilder->setPaths();
$customReportBuilder->browseImages();
