<?php
/**
 * REDCap External Module: Custom Template Engine
 * @author Luke Stevens lukestevens@hotmail.com https://github.com/lsgs/ 
 */
if (is_null($module) || !($module instanceof MCRI\CustomTemplateEngine\CustomTemplateEngine)) { exit(); }
header("Content-Type: application/json");
echo \json_encode($module->fillAndSave());
