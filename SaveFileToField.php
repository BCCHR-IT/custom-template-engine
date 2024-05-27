<?php
/**
 * Initialize Custom Template Engine object, and call method to save report to a field.
 */

require_once "vendor/autoload.php";

use Dompdf\Dompdf;

$customTemplateEngine = new \BCCHR\CustomTemplateEngine\CustomTemplateEngine();
$customTemplateEngine->setPaths();

$header = REDCap::filterHtml(preg_replace("/&nbsp;/", " ", $_POST["header-editor"]));
$footer = REDCap::filterHtml(preg_replace("/&nbsp;/", " ", $_POST["footer-editor"]));
$main = REDCap::filterHtml(preg_replace("/&nbsp;/", " ", $_POST["editor"]));

$filename = filter_input(INPUT_POST, 'filename', FILTER_SANITIZE_SPECIAL_CHARS);
$record = filter_input(INPUT_POST, 'record', FILTER_SANITIZE_SPECIAL_CHARS);

if (isset($main) && !empty($main))
{
    $event_name = htmlspecialchars($_POST["save-report-to-event-val"], ENT_QUOTES);
    $field_name = htmlspecialchars($_POST["save-report-to-field-val"], ENT_QUOTES);

    if (empty($field_name))
    {
        print json_encode(array("error" => "Cannot save to an empty field!"));
        return;
    }

    if (!empty($event_name))
    {
        $event_id = REDCap::getEventIdFromUniqueEvent($event_name);
    }
    else 
    {
        // SQL to retrieve the first event ID of the first event in the project.
        $sql = "SELECT event_id FROM redcap_events_metadata 
                join redcap_events_arms on
                redcap_events_metadata.arm_id = redcap_events_arms.arm_id
                where redcap_events_arms.project_id = ?
                order by event_id asc
                limit 1";

        $result = $customTemplateEngine->query($sql, array($customTemplateEngine->getProjectId()));

        if (!$result)
        {
            $error_msg = "Trouble retrieving first event ID for saving report to a record field, for a classic project.";
            REDCap::logEvent("Custom Template Engine - $error_msg", "", "", $record);
            print json_encode(array("error" => $error_msg));
            return;
        }

        $row = $result->fetch_assoc();
        $event_id = $row["event_id"];
    }
    
    // If longitudinal, check that field exists on chosen event.
    if (REDCap::isLongitudinal() && !$customTemplateEngine->checkFieldInEvent($field_name, $event_id))
    {
        print json_encode(array("error" => "$field_name is not a valid field on " . (empty($event_name) ? "the first event" : $event_name)));
        return;
    }

    $dompdf = new Dompdf();
    $pdf_content = $customTemplateEngine->createPDF($dompdf, $header, $footer, $main);

    if (!$customTemplateEngine->saveFileToField($filename, $pdf_content, $field_name, $record, $event_id))
    {
        REDCap::logEvent("Custom Template Engine - Failed to Save Report to Field!", "Field name: $field_name", "", $record);
        print json_encode(array("error" => "An unknown error occured. Please contact the BCCHR REDCap team."));
        return;
    }

    print json_encode(array("success" => true));
}
