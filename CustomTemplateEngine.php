<?php

namespace MCRI\CustomTemplateEngine;

/**
 * Require Template Engine Template class, 
 * and autoload.php from Composer.
 */
require_once "Template.php";

use REDCap;
use Project;
use Dompdf\Dompdf;
use DOMDocument;
use HtmlPage;
use ZipArchive;

class CustomTemplateEngine extends \ExternalModules\AbstractExternalModule 
{
    /**
     * Class variables.
     * 
     * @var String $templates_dir       Directory to store templates.
     * @var String $compiled_dir        Directory to store templates compiled by Smarty template engine.
     * @var String $img_dir             Directory to store images.
     * @var String $pid                 Project id of current REDCap project.
     * @var String $userid              ID of current user.
     */
    private $templates_dir;
    private $compiled_dir;
    private $temp_dir;
    private $img_dir;
    private $pid;
    private $userid;
    private $Proj;

    /**
     * Initialize class variables.
     */
    function __construct()
    {
        parent::__construct();
        $this->userid = strtolower(USERID);
        /**
         * External Module functions to get module settings. 
         */
        $this->templates_dir = $this->getSystemSetting("templates-folder");
        $this->compiled_dir = $this->getSystemSetting("compiled-templates-folder");
        $this->temp_dir = $this->getSystemSetting("temp-folder");
        $this->img_dir = $this->getSystemSetting("img-folder");
        $this->pid = $this->getProjectId();

        if (!empty($this->pid))
        {
            $this->Proj = new Project($this->pid);
        }

        /**
         * Checks and adds trailing directory separator
         */
        if (substr($this->templates_dir, -1) != DIRECTORY_SEPARATOR)
        {
            $this->templates_dir = $this->templates_dir . DIRECTORY_SEPARATOR;
        }

        if (substr($this->compiled_dir, -1) != DIRECTORY_SEPARATOR)
        {
            $this->compiled_dir = $this->compiled_dir . DIRECTORY_SEPARATOR;
        }

        if (substr($this->temp_dir, -1) != DIRECTORY_SEPARATOR)
        {
            $this->temp_dir = $this->temp_dir . DIRECTORY_SEPARATOR;
        }

        if (substr($this->img_dir, -1) != DIRECTORY_SEPARATOR)
        {
            $this->img_dir = $this->img_dir . DIRECTORY_SEPARATOR;
        }
    }

    /**
     * Creates the templates, compiled templates, and images folders for the module, if they don't exist.
     * 
     * Creates the templates, compiled templates, and images folders for the module, if they don't exist. Exits on an error if any of the 
     * module folders haven't been configured, or if any of the locations aren't writable.
     * 
     * @since 1.0
     * @access private
     */
    private function createModuleFolders()
    {
        if (empty($this->templates_dir))
        {
            exit("<div class='red'><b>ERROR</b> Templates directory has not been set. Please contact your REDCap administrator.</div>");
        }
        else
        {
            if (!file_exists($this->templates_dir))
            {
                if (!mkdir($this->templates_dir, 0777, true))
                {
                    exit("<div class='red'><b>ERROR</b> Unable to create directory $this->templates_dir to store templates. Please contact your systems administrator to make sure the location is writable.</div>");
                }
            }
        }

        if (empty($this->compiled_dir))
        {
            exit("<div class='red'><b>ERROR</b> Compiled templates directory has not been set. Please contact your REDCap administrator.</div>");
        }
        else
        {
            if (!file_exists($this->compiled_dir))
            {
                if (!mkdir($this->compiled_dir, 0777, true))
                {
                    exit("<div class='red'><b>ERROR</b> Unable to create directory $this->compiled_dir to store compiled templates. Please contact your systems administrator to  make sure the location is writable.</div>");
                }
            }
        }

        if (empty($this->temp_dir))
        {
            exit("<div class='red'><b>ERROR</b> Compiled templates directory has not been set. Please contact your REDCap administrator.</div>");
        }
        else
        {
            if (!file_exists($this->temp_dir))
            {
                if (!mkdir($this->temp_dir, 0777, true))
                {
                    exit("<div class='red'><b>ERROR</b> Unable to create directory $this->temp_dir to store temporary files. Please contact your systems administrator to  make sure the location is writable.</div>");
                }
            }
        }

        if (empty($this->img_dir))
        {
            exit("<div class='red'><b>ERROR</b> Images directory has not been set. Please contact your REDCap administrator.</div>");
        }
        else
        {
            if (!file_exists($this->img_dir))
            {
                if (!mkdir($this->img_dir, 0777, true))
                {
                    exit("<div class='red'><b>ERROR</b> Unable to create directory $this->img_dir to store template. Please contact your systems administrator to  make sure the location is writable.</div>");
                }
            }
        }
    }

    /**
     * Initializes a CKeditor with all the appropriate plugins.
     * 
     * Injects Javascript to initialize the CKEditor in the given textarea element, alongside all its plugins,
     * adjusting its height according to the argument passed.
     * 
     * @param String $id    The id of the textarea element to replace with the editor.
     * @param Integer $height   The height of the editor in pixels.
     * @since 1.0
     * @access private
     */
    private function initializeEditor($id, $height)
    {
        ?>
        <script>
            CKEDITOR.plugins.addExternal('codemirror', '<?php print $this->getUrl("vendor/egorlaw/ckeditor_codemirror/plugin.js"); ?>');
            CKEDITOR.replace('<?php print $id;?>', {
                extraPlugins: 'codemirror',
                toolbar: [
                    { name: 'clipboard', items: [ 'Undo', 'Redo' ] },
                    { name: 'basicstyles', items: [ 'Bold', 'Italic', 'Underline', 'Strike', 'RemoveFormat'] },
                    { name: 'paragraph', items: [ 'NumberedList', 'BulletedList', '-', 'Outdent', 'Indent', '-'] },
                    { name: 'insert', items: [ 'Image', 'Table', 'HorizontalRule' ] },
                    { name: 'styles', items: [ 'Format', 'Font', 'FontSize' ] },
                    { name: 'colors', items: [ 'TextColor', 'BGColor', 'CopyFormatting' ] },
                    { name: 'align', items: [ 'JustifyLeft', 'JustifyCenter', 'JustifyRight', 'JustifyBlock' ] },
                    { name: 'source', items: ['Source', 'searchCode', 'Find', 'SelectAll'] },
                ],
                height: <?php print $height; ?>,
                bodyClass: 'document-editor',
                contentsCss: [ 'https://cdn.ckeditor.com/4.8.0/full-all/contents.css', '<?php print $this->getUrl("app.css"); ?>' ],
                filebrowserBrowseUrl: '<?php print $this->getUrl("BrowseImages.php"); ?>&type=Images',
                filebrowserUploadUrl: '<?php print $this->getUrl("UploadImages.php"); ?>&type=Images',
                filebrowserUploadMethod: 'form',
                fillEmptyBlocks: false,
                extraAllowedContent: '*{*}',
                font_names: 'Arial/Arial, Helvetica, sans-serif; Times New Roman/Times New Roman, Times, serif; Courier; DejaVu; DejaVu Sans, sans-serif'
            });
        </script>
        <?php
    }

    /**
     * Checks the module permissions.
     * 
     * Checks if the necessary directorys have been created, and whether the user has data export and report rights,
     * which are needed to access the module's functionality.
     * 
     * @since 1.0
     * @access private
     */
    private function checkPermissions()
    {
        if (empty($this->templates_dir) || !file_exists($this->templates_dir))
        {
            exit("<div class='red'><b>ERROR</b> Templates directory has not been set, or doesn't exist. Please contact your REDCap administrator.</div>");
        }

        if (empty($this->compiled_dir) || !file_exists($this->compiled_dir))
        {
            exit("<div class='red'><b>ERROR</b> Compiled templates directory has not been set, or doesn't exist. Please contact your REDCap administrator.</div>");
        }

        if (empty($this->img_dir) || !file_exists($this->img_dir))
        {
            exit("<div class='red'><b>ERROR</b> Images directory has not been set, or doesn't exist. Please contact your REDCap administrator.</div>");
        }

        $rights = REDCap::getUserRights($this->userid);
        if ($rights[$this->userid]["data_export_tool"] === "0" || !$rights[$this->userid]["reports"]) 
        {
            exit("<div class='red'>You don't have permission to view this page</div><a href='" . $this->getUrl("index.php") . "'>Back to Front</a>");
        }
    }

    /**
     * Retrieves the latest repeatable instance for a record on a specified event.
     * 
     * @since 3.1
     */
    private function getLatestRecordInstance($record, $event_id = null)
    {
        $record_data = REDCap::getData("json", $record, null, $event_id, null, TRUE, FALSE, TRUE, null, TRUE);
        $json = json_decode($record_data, true);

        foreach($json as $index => $event_data)
        {
            $redcap_repeat_instance = $event_data["redcap_repeat_instance"];
            if (isset($redcap_repeat_instance) && !empty($redcap_repeat_instance))
            { 
                $instance = $redcap_repeat_instance == 1 ? null : $redcap_repeat_instance; // First instance is represented by null in db
            }
        }

        return $instance;
    }

    /**
     * Retrieves the latest repeatable instance for a record, if [event_id][field_name] is on a repeatable instrument/event, else return null.
     * 
     * @since 3.1
     */
    private function getLatestRepeatableInstance($record, $event_id, $field_name)
    {
        /**
         * Check if field is on a repeatable form. If yes, then return latest instance, else return null.
         */
        if($this->Proj->hasRepeatingFormsEvents())
        {
            $repeating_events = $this->Proj->getRepeatingFormsEvents();

            $instruments = $repeating_events[$event_id];
            if ($instruments === "WHOLE") // repeat whole event
            {
                $query = $this->framework->createQuery();
                $query->add("select form_name from redcap_events_repeat where event_id = ?", [$event_id]);
                $result = $query->execute();

                if($result->num_rows > 0)
                {
                    while ($row = $result->fetch_assoc())
                    {
                        $instrument = $row["form_name"];
                        $fields = REDCap::getFieldNames($instrument); // Get fields in repeatable instrument

                        if (in_array($field_name, $fields)) // Check if field is part of repeatable instrument
                        {
                            return $this->getLatestRecordInstance($record, $event_id);
                        }
                    }
                }
            }
            else if (is_array($instruments)) // repeate instruments on event
            {
                foreach($instruments as $instrument => $custom_repeat_label) // iterate through instruments on repeatable event
                {
                    $fields = REDCap::getFieldNames($instrument); // Get fields in repeatable instrument
                    if (in_array($field_name, $fields)) // Check if field is part of repeatable instrument
                    {
                        return $this->getLatestRecordInstance($record, $event_id);
                    }
                }
            }
        }

        return null;
    }

    /**
     * Checks that the field exists on the given event
     * 
     * @since 3.1
     */
    public function checkFieldInEvent($field_name, $event_id)
    {
        $query = $this->framework->createQuery();
        $query->add("SELECT 1 from redcap_metadata
                    join redcap_events_forms 
                    on redcap_metadata.form_name = redcap_events_forms.form_name
                    where redcap_events_forms.event_id = ? and redcap_metadata.field_name = ?", [$event_id, $field_name]);

        $result = $query->execute();
        
        if ($result)
        {
            return $result->num_rows > 0;
        }

        return false;
    }
    
    /**
     * Saves a file to a REDCap field in the project. Assumes the field is a file upload field. Returns false on failure, and true otherwise.
     * Currently no compatible with repeating events.
     */
    public function saveFileToField($filename, $file_contents, $field_name, $record, $event_id)
    {
        // Save file to edocs tables in the REDCap database
        $database_success = FALSE;
        $upload_success = FALSE;
        $docs_id = 0;

        $dummy_file_name = $filename . ".pdf";
        $dummy_file_name = preg_replace("/[^a-zA-Z-._0-9]/","_",$dummy_file_name);
        $dummy_file_name = str_replace("__","_",$dummy_file_name);
        $dummy_file_name = str_replace("__","_",$dummy_file_name);

        $stored_name = date('YmdHis') . "_pid" . $this->pid . "_" . generateRandomHash(6) . ".pdf";

        $upload_success = file_put_contents(EDOC_PATH . $stored_name, $file_contents);

        if ($upload_success !== FALSE) 
        {
            $dummy_file_size = $upload_success;

            $query = $this->framework->createQuery();
            $query->add("INSERT INTO redcap_edocs_metadata (stored_name,mime_type,doc_name,doc_size,file_extension,project_id,stored_date) 
                        VALUES ('$stored_name','application/pdf',?,?,'pdf',?,?)", [$dummy_file_name, $dummy_file_size, $this->pid, date('Y-m-d H:i:s')]);
    
            if ($query->execute()) 
            {
                $docs_id = db_insert_id();
                
                // Always save report to the latest repeatable instance, otherwise null
                $instance = $this->getLatestRepeatableInstance($record, $event_id, $field_name);

                // See if field has had a previous value. If so, update; if not, insert.
                $query = $this->framework->createQuery();
                $query->add("SELECT value FROM redcap_data
                            WHERE project_id = ? AND record = ? AND event_id = ? AND field_name = ?", [$this->pid, $record, $event_id, $field_name]);

                if (!isset($instance))
                {
                    $query->add("AND instance is NULL");
                }
                else
                {
                    $query->add("AND instance = ?", [$instance]);
                }
                
                $result = $query->execute();

                if ($result && $result->num_rows > 0) // row exists
                {
                    // Set the file as "deleted" in redcap_edocs_metadata table, but don't really delete the file or the table entry (unless the File Version History is enabled for the project)
                    if ($GLOBALS['file_upload_versioning_global_enabled'] == '' && $this->Proj->project['file_upload_versioning_enabled'] != '1')
                    {
                        while ($row = $result->fetch_assoc()) {
                            $id = $row["value"];
                        }

                        $query = $this->framework->createQuery();
                        $query->add("UPDATE redcap_edocs_metadata SET delete_date = ? WHERE doc_id = ?", [NOW, $id]);
                        $query->execute();
                    }

                    $query = $this->framework->createQuery();
                    $query->add("UPDATE redcap_data SET value = ? WHERE project_id = ? AND record = ? AND event_id = ? AND field_name = ?", [$docs_id, $this->pid, $record, $event_id, $field_name]);

                    if (!isset($instance))
                    {
                        $query->add("AND instance is NULL");
                    }
                    else
                    {
                        $query->add("AND instance = ?", [$instance]);
                    }
                }
                else // row did not exist
                {
                    // If this is a longitudinal project and this file is being added to an event without data,
                    // then add a row for the record ID field too (so it doesn't get orphaned).
                    if ($this->Proj->longitudinal) 
                    {
                        $query = $this->framework->createQuery();
                        $query->add("SELECT 1 FROM redcap_data WHERE project_id = ? AND record = ? AND event_id = ?", [$this->pid, $record, $event_id]);

                        if ($instance > 1)
                        {
                            $query->add("AND instance = ?", [$instance]);
                        }
                        else
                        {
                            $query->add("AND instance is NULL");
                        }

                        $query->add("LIMIT 1");

                        $result = $query->execute();

                        if ($result && $result->num_rows == 0) 
                        {
                            $instance = $instance > 1 ? $instance : "NULL";
                            $query = $this->framework->createQuery();
                            $query->add("INSERT INTO redcap_data (project_id, event_id, record, field_name, value, instance) VALUES (?, ?, ?, ?, ?, ?)",
                                        [$this->pid, $event_id, $record, $this->Proj->table_pk, $record, $instance]);
                            $query->execute();
                        }
                    }
        
                    // Add an entry in redcap_data that contains the edoc ID
                    $query = $this->framework->createQuery();
                    if (!isset($instance))
                    {
                        $query->add("INSERT INTO redcap_data (project_id, event_id, record, field_name, value, instance) VALUES (?, ?, ?, ?, ?, ?)",
                                    [$this->pid, $event_id, $record, $field_name, $docs_id, null]);
                    }
                    else
                    {
                        $query->add("INSERT INTO redcap_data (project_id, event_id, record, field_name, value, instance) VALUES (?, ?, ?, ?, ?, ?)",
                                    [$this->pid, $event_id, $record, $field_name, $docs_id, $instance]);
                    }
                }

                if ($query->execute())
                {
                    // Logging event as DOC_UPLOAD allows file history to be built.
                    $redcap_log_event_table = method_exists('\REDCap', 'getLogEventTable') ? REDCap::getLogEventTable($this->pid) : "redcap_log_event";
                    $current_timestamp = date("YmdHis");
                    $ip = $_SERVER["REMOTE_ADDR"];
                    $description = isset($instance) ? "[instance = $instance],\n$field_name = '$docs_id'" : "$field_name = '$docs_id'";
                    $sql = str_replace("'", "''", $query->getSQL());

                    $query = $this->framework->createQuery();
                    $query->add("INSERT INTO $redcap_log_event_table (ts, user, ip, page, project_id, event, object_type, sql_log, pk, event_id, data_values, description) VALUES (?, ?, ?, 'ExternalModules/index.php', ?, 'DOC_UPLOAD', 'redcap_data', ?, ?, ?, ?, 'Upload Document')",
                                [$current_timestamp, $this->userid, $ip, $this->pid, $sql, $record, $event_id, $description]);
                    $query->execute();
                }
            }
        }

        return $docs_id; // 0 if could not upload file
    }

    /**
     * Helper function that deletes a file from the File Repository, if REDCap data about it fails
     * to be inserted to the database.Stolen code from redcap version/FileRepository/index.php.
     * 
     * @param String $file     Name of file to delete
     * @since 1.0
     * @access private
     */
    private function deleteRepositoryFile($file)
    {
        global $edoc_storage_option,$wdc,$webdav_path;
        if ($edoc_storage_option == '1') {
            // Webdav
            $wdc->delete($webdav_path . $file);
        } elseif ($edoc_storage_option == '2') {
            // S3
            global $amazon_s3_key, $amazon_s3_secret, $amazon_s3_bucket;
            $s3 = new S3($amazon_s3_key, $amazon_s3_secret, SSL); if (isset($GLOBALS['amazon_s3_endpoint']) && $GLOBALS['amazon_s3_endpoint'] != '') $s3->setEndpoint($GLOBALS['amazon_s3_endpoint']);
            $s3->deleteObject($amazon_s3_bucket, $file);
        } else {
            // Local
            @unlink(EDOC_PATH . $file);
        }
    }

    /**
     * Saves a file to REDCap's File Repository. Based off stolen code from redcap version/FileRepository/index.php
     * with several modifications
     * 
     * @param String $filename         Name of file
     * @param String $file_contents    Contents of file
     * @param String $file_extension File extension
     * @return bool always returns true
     * @see CustomTemplateEngine::deleteRepositoryFile() For deleting a file from the repository, if metadata failed to create.
     * @since 3.0
     */
    private function saveToFileRepository($filename, $file_contents, $file_extension)  
    {   
        // Upload the compiled report to the File Repository
        $errors = array();
        $database_success = FALSE;
        $upload_success = FALSE;

        $dummy_file_name = $filename;
        $dummy_file_name = preg_replace("/[^a-zA-Z-._0-9]/","_",$dummy_file_name);
        $dummy_file_name = str_replace("__","_",$dummy_file_name);
        $dummy_file_name = str_replace("__","_",$dummy_file_name);

        $stored_name = date('YmdHis') . "_pid" . $this->pid . "_" . generateRandomHash(6) . ".$file_extension";

        $upload_success = file_put_contents(EDOC_PATH . $stored_name, $file_contents);

        if ($upload_success !== FALSE) 
        {
            $dummy_file_size = $upload_success;
            $dummy_file_type = "application/$file_extension";
            
            $file_repo_name = date("Y/m/d H:i:s");

            $query = $this->framework->createQuery();
            $query->add("INSERT INTO redcap_docs (project_id,docs_date,docs_name,docs_size,docs_type,docs_comment,docs_rights) VALUES (?, CURRENT_DATE, ?, ?, ?, ?, NULL)",
                        [$this->pid, "$dummy_file_name.$file_extension", $dummy_file_size, $dummy_file_type, "$file_repo_name - $filename ($this->userid)"]);
                            
            if ($query->execute()) 
            {
                $docs_id = db_insert_id();

                $query = $this->framework->createQuery();
                $query->add("INSERT INTO redcap_edocs_metadata (stored_name,mime_type,doc_name,doc_size,file_extension,project_id,stored_date) VALUES(?,?,?,?,?,?,?)",
                            [$stored_name, $dummy_file_type, "$dummy_file_name.$file_extension", $dummy_file_size, $file_extension, $this->pid, date('Y-m-d H:i:s')]);
                            
                if ($query->execute()) 
                {
                    $doc_id = db_insert_id();

                    $query = $this->framework->createQuery();
                    $query->add("INSERT INTO redcap_docs_to_edocs (docs_id,doc_id) VALUES (?,?)", [$docs_id, $doc_id]);
                                
                    if ($query->execute()) 
                    {
                        $context_msg_insert = "{$lang['docs_22']} {$lang['docs_08']}";

                        // Logging
                        REDCap::logEvent("Custom Template Engine - Uploaded document to file repository", "Successfully uploaded $filename");
                        $context_msg = str_replace('{fetched}', '', $context_msg_insert);
                        $database_success = TRUE;
                    } 
                    else 
                    {
                        /* if this failed, we need to roll back redcap_edocs_metadata and redcap_docs */
                        $query = $this->framework->createQuery();
                        $query->add("DELETE FROM redcap_edocs_metadata WHERE doc_id=?", [$doc_id]);
                        $query->execute();

                        $query = $this->framework->createQuery();
                        $query->add("DELETE FROM redcap_docs WHERE docs_id=?", [$docs_id]);
                        $query->execute();

                        $this->deleteRepositoryFile($stored_name);
                    }
                } 
                else
                {
                    /* if we failed here, we need to roll back redcap_docs */
                    $query = $this->framework->createQuery();
                    $query->add("DELETE FROM redcap_docs WHERE docs_id=?", [$docs_id]);
                    $query->execute();
                    
                    $this->deleteRepositoryFile($stored_name);
                }
            }
            else 
            {
                /* if we failed here, we need to delete the file */
                $this->deleteRepositoryFile($stored_name);
            }            
        }

        if ($database_success === FALSE) 
        {
            $context_msg = "<b>{$lang['global_01']}{$lang['colon']} {$lang['docs_47']}</b><br>" . $lang['docs_65'] . ' ' . maxUploadSizeFileRespository().'MB'.$lang['period'];
                            
            if (SUPER_USER) 
            {
                $context_msg .= '<br><br>' . $lang['system_config_69'];
            }

            return $context_msg;
        }

        return true;
    }

    /**
     * Formats a report to give to DOMPdf, with appropriate CSS
     * and scripts to add page numbers/timestamps, at the bottom of the page.
     * 
     * @param String $header    Header contents of report
     * @param String $footer    Footer contents of report
     * @param String $main      Main content of report
     * @since 3.0
     * @return String   PDF contents
     */
    private function formatPDFContents($header = "", $footer = "", $main)
    {
        if (isset($main) && !empty($main))
        {
            $doc = new DOMDocument();
            $doc->loadHtml("
                <!DOCTYPE html>
                <html>
                    <head>
                        <meta http-equiv='Content-Type' content='text/html; charset=utf-8'/>
                    </head>
                    <body>
                        <header>$header</header>
                        <footer>$footer</footer>
                        <main>$main</main>
                        <script type='text/php'>
                            // Add page number and timestamp to every page
                            if (isset(\$pdf)) { 
                                \$pdf->page_script('
                                    \$font = \$fontMetrics->get_font(\"Arial, Helvetica, sans-serif\", \"normal\");
                                    \$size = 12;
                                    \$pageNum = \"Page \" . \$PAGE_NUM . \" of \" . \$PAGE_COUNT;
                                    \$y = 750;
                                    \$pdf->text(520, \$y, \$pageNum, \$font, \$size);
                                    \$pdf->text(36, \$y, date(\"Y-m-d H:i:s\", time()), \$font, \$size);
                                ');
                            }
                        </script>
                    </body>
                </html>
            ");

            // DOMPdf renders what's passed in, and if default font-size is used then
            // the editor will use what's in app.css. Set the general CSS to be 12px.
            // Any styling done by the user should appear as inline styling, which should
            // override this.
            if (!empty($header) && !empty($footer))
            {
                $style = $doc->createElement("style", "body, body > table { font-size: 12px; margin-top: 25px; } header { position: fixed; left: 0px; right: 0px; top: -100px; } footer { position: fixed; left: 0px; right: 0px; bottom: 0px; } @page { margin: 130px 50px; }");
            }
            else if (!empty($header))
            {
                $style = $doc->createElement("style", "body, body > table { font-size: 12px; margin-top: 15px; } header { position: fixed; left: 0px; top: -100px; } @page { margin: 130px 50px 50px 50px; }");
            }
            else if (!empty($footer))
            {
                $style = $doc->createElement("style", "body, body > table { font-size: 12px; margin-top: 15px; } footer { position: fixed; left: 0px; bottom: 0px; } @page { margin: 50px 50px 130px 50px; }");
            }
            else
            {
                $style = $doc->createElement("style", "body, body > table { font-size: 12px;} @page { margin: 50px 50px; }");
            }   
            
            $doc->appendChild($style);
        
            return $doc->saveHTML();
        }

        return "";
    }

    /**
     * Uploads images from file browser object to server.
     * 
     * Uploads images from file browser object to server, after performing 
     * validations. Error returned to user if upload failed. Upon success
     * log event in REDCap.
     * 
     * @since 3.1
     */
    public function uploadImages()
    {
        // Required: anonymous function reference number as explained above.
        $func_num = $_GET["CKEditorFuncNum"] ;
        // Optional: compare it with the value of `ckCsrfToken` sent in a cookie to protect your server side uploader against CSRF.
        // Available since CKEditor 4.5.6.
        $token = $_POST["ckCsrfToken"] ;
        $cookie_token = $_COOKIE["ckCsrfToken"];

        // url of the image to return
        $url = "";
        // error message, empty if none
        $message = "";

        if ($token === $cookie_token)
        {
            // Check the $_FILES array and save the file. Assign the correct path to a variable ($url).
            if (isset($_FILES["upload"]) && !empty($_FILES["upload"]))
            {
                $upload = $_FILES["upload"];
                if ($upload["error"] == UPLOAD_ERR_OK)
                {
                    $tmp_name = $upload["tmp_name"];
                    $check = getimagesize($tmp_name);
                    
                    if ($check !== false)
                    {
                        $name = pathinfo(basename($upload["name"]));
                        $filename = str_replace(" ", "_", $name["filename"]) . "_" . $_GET["pid"] . "." . $name["extension"];

                        if (move_uploaded_file($tmp_name, $this->img_dir . $filename))
                        {
                            $realpath = realpath($this->img_dir);
                            $publicly_accessible_start_pos = strpos($realpath, "redcap");
                            $path = substr($realpath, $publicly_accessible_start_pos);

                            $url = "https://" . $_SERVER["SERVER_NAME"] . "/$path/" . $filename;
                            REDCap::logEvent("Custom Template Engine - Photo uploaded", $filename);
                        }
                        else
                        {
                            $lastErr = error_get_last();
                            if ($lastErr != NULL)
                            {
                                $message = "ERROR: " . $lastErr["message"];
                            }
                            else
                            {
                                $message = "ERROR: Unable to move uploaded file to directory.";
                            }
                        }
                    }
                    else
                    {
                        $message = "ERROR: File is not an image.";
                    }
                }
                else
                {
                    $message = "ERROR: error uploading file.";
                }
            }
            else
            {
                $message = "ERROR: error uploading file.";
            }
        }
        else
        {
            $message = "ERROR: ckCsrfToken not valid";
        }
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>Img Upload</title>
        </head>
        <script type="text/javascript"><?php print "window.parent.CKEDITOR.tools.callFunction($func_num, '$url', \"$message\");"; ?></script>
        <body>
        </body>
        </html> 
        <?php
    }

    /**
     * Generates HTMl to display all images uploaded to the server, that are specific to the project.
     * 
     * Retrieve images for the current REDCap project and generate HTML to display, and Javascript
     * that will return the image url on click.
     * 
     * @since 3.1
     */
    public function browseImages()
    {
        $proj_imgs = array_filter(scandir($this->img_dir), function ($img) {
            return strpos($img, "_" . $this->pid) !== FALSE;
        });
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>Browsing Files</title>
            <link href="<?php print $this->getUrl("app.css"); ?>" rel="stylesheet" type="text/css">
            <!-- Latest compiled and minified CSS -->
            <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">
            <!-- Optional theme -->
            <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap-theme.min.css" integrity="sha384-rHyoN1iRsVXV4nD0JutlnGaslCJuC7uwjduW9SVrLvRYooPp2bWYgmgJQIXwl/Sp" crossorigin="anonymous">
            <!-- Latest compiled and minified JavaScript -->
            <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js" integrity="sha384-Tc5IQib027qvyjSMfHjOMaLkfuWVxZxUPnCJA7l2mCWNIpG9mGCD8wGNIcPD7Txa" crossorigin="anonymous"></script>
            <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
            <script>
                // Helper function to get parameters from the query string.
                function getUrlParam( paramName ) {
                    var reParam = new RegExp( "(?:[\?&]|&)" + paramName + "=([^&]+)", "i" );
                    var match = window.location.search.match( reParam );

                    return ( match && match.length > 1 ) ? match[1] : null;
                }
                // Simulate user action of selecting a file to be returned to CKEditor.
                function returnFileUrl(filename) {
                    var funcNum = getUrlParam("CKEditorFuncNum");
                    window.opener.CKEDITOR.tools.callFunction(funcNum, filename);
                    window.close();
                }
            </script>
        </head>
        <body>
            <div class="row" style="margin:20px">
                <h4>IMAGE GALLERY</h4>
                <h6>Click on your image to import it into the editor</h6>
            </div>
            <hr/>
            <div style="margin:20px">
                <?php
                    $all_imgs = array();
                    foreach($proj_imgs as $img)
                    {
                        $realpath = realpath($this->img_dir);
                        $publicly_accessible_start_pos = strpos($realpath, "redcap");
                        $path = substr($realpath, $publicly_accessible_start_pos);
                        
                        array_push(
                            $all_imgs,
                            array(
                                "url" => "https://" . $_SERVER["SERVER_NAME"] . "/$path/" . $img,
                                "name" => $img
                            )
                        );
                    }
                    for($i = 0; $i < sizeof($all_imgs); $i++)
                    {
                        if ($i%6 == 0 && $i == 0)
                        {
                            print "<div class='row'>";
                        }
                        else if ($i%6 == 0 && $i != 0 && $i != sizeof($all_imgs))
                        {
                            print "</div><br/><div class='row'>";
                        }

                        $arr = $all_imgs[$i];
                        print "
                        <div class='col-md-2'>
                            <div class='img-file' img-url ='" . $arr["url"]. "'>
                                <div style='background-image: url(\"". $arr["url"]. "\");'></div>
                                <p style='background-color:#286090;color:white'>" . $arr["name"] . "</p>
                            </div>
                        </div>
                        ";

                        if ($i == sizeof($all_imgs) - 1)
                        {
                            print "</div>";
                        }
                    }
                ?>
            </div>
            <script>
                $(".img-file").click(function () {
                    returnFileUrl($(this).attr("img-url"));
                });
            </script>
        </body>
        </html>
        <?php
    }

    /**
     * Deletes a template from the server.
     * 
     * Template to delete is passed via HTTP POST. Method deletes
     * file from server, and logs event.
     * 
     * @since 2.0
     * @return Boolean If template was deleted return TRUE, else return FALSE.
     */
    public function deleteTemplate()
    {
        $templateToDelete = $_POST["templateToDelete"];
        if (unlink($this->templates_dir . $templateToDelete))
        {
            REDCap::logEvent("Custom Template Engine - Deleted template", $templateToDelete);
            return TRUE;
        }
        else
        {
            return FALSE;
        }
    }

    /**
     * Saves a template.
     * 
     * Retrieves body, header, and footer contents of template passed via HTTP POST.
     * Performs validation on the template contents, and saves regardless. If there's
     * any validation errors then the template is saved on server as '<template name>_<pid> - INVALID.html'.
     * 
     * @since 3.0
     * @return Array An array containing any validation errors, and the template's body, header, and footer contents.
     */
    public function saveTemplate()
    {
        $header = REDCap::filterHtml(preg_replace(array("/&lsquo;/", "/&rsquo;/", "/&nbsp;/"), array("'", "'", " "), $_POST["header-editor"]));
        $footer = REDCap::filterHtml(preg_replace(array("/&lsquo;/", "/&rsquo;/", "/&nbsp;/"), array("'", "'", " "), $_POST["footer-editor"]));
        $data = REDCap::filterHtml(preg_replace(array("/&lsquo;/", "/&rsquo;/", "/&nbsp;/"), array("'", "'", " "), $_POST["editor"]));

        $name = trim($_POST["templateName"]);
        $action = $_POST["action"];

        // Check if template has content
        if (empty($data))
        {
            $HtmlPage = new HtmlPage();
            $HtmlPage->PrintHeaderExt();
            exit("<div class='yellow'>You shouldn't be seeing this page. You've likely resubmitted your form without any data</div><a href='" . $this->getUrl("index.php") . "'>Back to Front</a>");
            $HtmlPage->PrintFooterExt();
        }
        // Template name cannot have director separator in it
        else if (strpos($name, "/") !== FALSE || strpos($name, "\\") !== FALSE)
        {
            $other_errors[] = "<b>ERROR</b> You cannot have '/' or '\\' in your template name! Template was not saved!";
            $filename = $name;

            if ($action == "edit")
            {
                $currTemplateName = $_POST["currTemplateName"];
            }
        }
        else
        {
            // Validate Template
            $template = new Template($this->templates_dir, $this->compiled_dir);

            $template_errors = $template->validateTemplate($data);
            $header_errors = $template->validateTemplate($header);
            $footer_errors = $template->validateTemplate($footer);
               
            $doc = new DOMDocument();
            $doc->loadHTML("
                <html>
                    <head>
                        <meta http-equiv='Content-Type' content='text/html;charset=utf-8'/>
                    </head>
                    <body>
                        <header>$header</header>
                        <footer>$footer</footer>
                        <main>$data</main>
                    </body>
                </html>
            ");

            // Creating a new template
            if ($action === "create")
            {  
                /**
                 * If template already exists, return error, if not save template.
                 */
                if (!file_exists("$this->templates_dir{$name}_$this->pid.html") && !file_exists("$this->templates_dir{$name}_{$this->pid} - INVALID.html"))
                {
                    $filename = !empty($template_errors) || !empty($header_errors) || !empty($footer_errors) ? "{$name}_{$this->pid} - INVALID.html" : "{$name}_$this->pid.html";
                    if ($doc->saveHTMLFile($this->templates_dir . $filename) === FALSE)
                    {
                        $other_errors[] = "<b>ERROR</b> Unable to save template. Please contact your REDCap administrator";
                    }
                    else
                    {
                        /**
                         * Since the template has been saved:
                         *  - All changes are not edits
                         *  - It's current template name = the filename it was saved under
                         */
                        $action = "edit";
                        $currTemplateName = $filename;
                        REDCap::logEvent("Custom Template Engine - Template created", $filename);
                    }
                }
                else
                {
                    $other_errors[] = "<b>ERROR</b> Template already exists! Please choose another name";
                    $filename = $name;
                }
            }
            // Editing an existing template
            else
            {
                /**
                 * If template doesn't exist, return error.
                 * Else if template names are the same save the template.
                 * Else, if the new template name is already in use return error, else save template and rename.
                 */
                $currTemplateName = $_POST["currTemplateName"];
                if (file_exists($this->templates_dir . $currTemplateName))
                {
                    if ($currTemplateName == "{$name}_{$this->pid}.html" || $currTemplateName == "{$name}_{$this->pid} - INVALID.html")
                    {
                        if ($doc->saveHTMLFile($this->templates_dir . $currTemplateName) === FALSE)
                        {
                            $other_errors[] = "<b>ERROR</b> Unable to save template. Please contact your REDCap administrator";
                        }
                        else
                        {
                            REDCap::logEvent("Custom Template Engine - Template edited", $currTemplateName);
                            if ((!empty($template_errors) || !empty($header_errors) || !empty($footer_errors)) && strpos($currTemplateName, " - INVALID") === FALSE)
                            {
                                $filename = strpos($currTemplateName, " - INVALID") !== FALSE ? $currTemplateName : str_replace(".html", " - INVALID.html", $currTemplateName);
                                rename($this->templates_dir. $currTemplateName, $this->templates_dir . $filename);
                            }
                            else if (strpos($currTemplateName, " - INVALID") !== FALSE)
                            {
                                $filename = str_replace(" - INVALID", "", $currTemplateName);
                                rename($this->templates_dir. $currTemplateName, $this->templates_dir . $filename);
                            }
                            $currTemplateName = $filename;
                        }
                    }
                    else if (!file_exists("$this->templates_dir{$name}_$this->pid.html") && !file_exists("$this->templates_dir{$name}_{$this->pid} - INVALID.html") )
                    {
                        if ($doc->saveHTMLFile($this->templates_dir . $currTemplateName) === FALSE)
                        {
                            $other_errors[] = "<b>ERROR</b> Unable to save template. Please contact your REDCap administrator";
                        }
                        else
                        {
                            $filename = !empty($template_errors) || !empty($header_errors) || !empty($footer_errors) ? "{$name}_$this->pid - INVALID.html" : "{$name}_$this->pid.html";
                            rename($this->templates_dir. $currTemplateName, $this->templates_dir . $filename);
                            REDCap::logEvent("Custom Template Engine - Template edited", "Renamed template from '$currTemplateName' to '$filename'");
                            $currTemplateName = $filename;
                        }
                    }
                    else 
                    {
                        $other_errors[] = "<b>ERROR</b> Template already exists! Please choose another name";
                        $filename = $name;
                    }
                }
                else
                {
                    $other_errors[] = "<b>ERROR</b> You're editing a template that doesn't exist! Please contact your REDCap administrator about this";
                    $filename = $name;
                }
            }
        }

        /**
         * Check for any errors
         */
        if (!empty($header_errors))
        {
            $errors["headerErrors"] = $header_errors;
        }

        if (!empty($footer_errors))
        {
            $errors["footerErrors"] = $footer_errors;
        }

        if (!empty($template_errors))
        {
            $errors["templateErrors"] = $template_errors;
        }
        
        if (!empty($other_errors))
        {
            $errors["otherErrors"] = $other_errors;
        }
        
        return array(
            "errors" => $errors,
            "redirect" => $this->getUrl("index.php") . "&created=1",
            "currTemplateName" => $currTemplateName
        );
    }

    /**
     * Use DOMPDF to format the PDF contents, and return it.
     * 
     * @since 3.1
     */
    public function createPDF($dompdf_obj, $header, $footer, $main, $fileOrTemplateName)
    {
        $contents = $this->formatPDFContents($header, $footer, $main);

        // Add page numbers to the footer of every page
        $dompdf_obj->set_option("isHtml5ParserEnabled", true);
        $dompdf_obj->set_option("isPhpEnabled", true);
        $dompdf_obj->loadHtml($contents);

        $dompdf_obj->set_option('isRemoteEnabled', TRUE);

        // Setup the paper size and orientation
        list($paperSize, $paperOrientation) = $this->getPaperSettings($fileOrTemplateName);
        $dompdf_obj->setPaper($paperSize, $paperOrientation);

        // Render the HTML as PDF
        $dompdf_obj->render();

        return $dompdf_obj->output();
    }

    /**
     * Outputs a PDF of a report to browser.
     * 
     * Retrieves body, header, and footer contents of template passed via HTTP POST.
     * Formats the contents within the PDF, and uses DOMPDF to output PDF to browser.
     * If saving to the File Repository is allowed, then a copy of the PDF is saved there.
     * Upon successful download, log in REDCap Returns Warning if main content editor is empty.
     * 
     * Code to save file to the File Repository was taken from redcap version/FileRepository/index.php.
     * 
     * @since 3.0
     */
    public function downloadTemplate()
    {
        $header = REDCap::filterHtml(preg_replace("/&nbsp;/", " ", $_POST["header-editor"]));
        $footer = REDCap::filterHtml(preg_replace("/&nbsp;/", " ", $_POST["footer-editor"]));
        $main = REDCap::filterHtml(preg_replace("/&nbsp;/", " ", $_POST["editor"]));

        $filename = $_POST["filename"];
        $record = $_POST["record"];

        if (isset($main) && !empty($main))
        {
            $dompdf = new Dompdf();
            $pdf_content = $this->createPDF($dompdf, $header, $footer, $main, $filename);

            if (!$this->getProjectSetting("save-report-to-repo"))
            {
                $saved = $this->saveToFileRepository($filename, $pdf_content, "pdf");
                if ($saved !== true)
                {
                    $HtmlPage = new HtmlPage();
                    $HtmlPage->PrintHeaderExt();
                    print "<div class='red'>" . $saved . "</div><a href='" . $this->getUrl("index.php") . "'>Back to Front</a>";
                    $HtmlPage->PrintFooterExt();
                }
            }

            $dompdf->stream($filename);
            REDCap::logEvent("Custom Template Engine - Downloaded Report", $filename , "" , $record);
        }
        else
        {
            $HtmlPage = new HtmlPage();
            $HtmlPage->PrintHeaderExt();
            print "<div class='yellow'>You shouldn't be seeing this page. You've likely resubmitted your form without any data</div><a href='" . $this->getUrl("index.php") . "'>Back to Front</a>";
            $HtmlPage->PrintFooterExt();
        }
    }

    /**
     * Fills multiple reports, saves them to a ZIP, then saves them to the File Repository, 
     * & outputs them for download.
     * 
     * @since 3.0
     */
    public function batchFillReports()
    {
        $errors = array();

        $records = $_POST["participantID"];
        $template_filename = $_POST['template'];
        $template = new Template($this->templates_dir, $this->compiled_dir);

        $zip_name = "{$this->temp_dir}reports.zip";
        $z = new ZipArchive();
        
        /**
         * Create ZIP
         */
        if ($z->open($zip_name, ZIPARCHIVE::CREATE) !== true)
        {
            $errors[] = "<p>ERROR</p> Could not create ZIP file";
        }
        else
        {
            try
            {
                /**
                 * Fill the template with each record data, then add them to the ZIP
                 */
                foreach($records as $record)
                {
                    $filename = basename($template_filename, "_$this->pid.html") . "_$record";
                    $filled_template = $template->fillTemplate($template_filename, $record);

                    $doc = new DOMDocument();
                    $doc->loadHTML($filled_template);

                    $header = $doc->getElementsByTagName("header")->item(0);
                    $footer = $doc->getElementsByTagName("footer")->item(0);
                    $main = $doc->getElementsByTagName("main")->item(0);
                    
                    $header = empty($header) ? "" : $doc->saveHTML($header);
                    $footer = empty($footer) ? "" : $doc->saveHTML($footer);
                    $main = $doc->saveHTML($main);
                
                    $contents = $this->formatPDFContents($header, $footer, $main);

                    if (!empty($contents))
                    {
                        $dompdf = new Dompdf();
                        $dompdf->set_option("isHtml5ParserEnabled", true);
                        $dompdf->set_option("isPhpEnabled", true);
                        $dompdf->loadHtml($contents);

                        $dompdf->set_option('isRemoteEnabled', TRUE);

                        // Setup the paper size and orientation
                        $dompdf->setPaper("letter", "portrait");
                        // Render the HTML as PDF
                        $dompdf->render();
                        $filled_template_pdf_content = $dompdf->output();

                        // Add PDF to ZIP
                        if ($z->addFromString("reports/$filename.pdf", $filled_template_pdf_content) !== true)
                        {
                            $errors[] = "<p>ERROR</p> Could not add $filename to the ZIP";
                            break;
                        }
                    }
                }

                if ($z->close() !== true)
                {
                    $errors[] = "<p>ERROR</p> Could not close ZIP file";
                }
                else if (!$this->getProjectSetting("save-report-to-repo"))
                {
                    $saved = $this->saveToFileRepository("reports", file_get_contents($zip_name), "zip");
                    if ($saved !== true)
                    {
                        $errors[] = $saved;
                    }
                }
            }
            catch (Exception $e)
            {
                $errors[] = "<b>ERROR</b> [" . $e->getCode() . "] LINE [" . $e->getLine() . "] FILE [" . $e->getFile() . "] " . str_replace("Undefined index", "Field name does not exist", $e->getMessage());
            }
        }

        /**
         * Download the ZIP if there are no errors
         */
        if (!empty($errors))
        {
            $HtmlPage = new HtmlPage();
            $HtmlPage->PrintHeaderExt();
            print "<p>Received the following errors, please contact your REDCap administrator</p>";
            print "<div class='red'>";
            foreach($errors as $error)
            {
                print "<p>$error</p>";
            }
            print "</div><a href='" . $this->getUrl("index.php") . "'>Back to Front</a>";
            $HtmlPage->PrintFooterExt();
        }
        else
        {
            header('Content-Description: File Transfer');
            header('Content-Type: application/zip');
            setCookie("fileDownloadToken", $_POST["download_token_value"], time() + 60, "", $_SERVER["SERVER_NAME"]); // Cannot specify path due to issue in IE that prevents cookie from being read. Default sets path as current directory.
            header('Content-Disposition: attachment; filename="'.basename($zip_name).'"');
            header('Content-length: '.filesize($zip_name));
            readfile($zip_name);
            REDCap::logEvent("Custom Template Engine - Downloaded Reports ", $template_filename , "" , implode(", ", $records));
        }
        unlink($zip_name);
        exit;
    }  

    /**
     * Get all fields in the project of type file.
     * 
     * @since 3.1
     */
    private function getAllFileFields()
    {
        $file_fields = array();
        $data_dictionary = REDCap::getDataDictionary("array");

        foreach ($data_dictionary as $field_name => $field_attributes)
        {
            if ($field_attributes["field_type"] == "file")
            {
                $field_label = $field_attributes["field_label"];
                $file_fields[$field_name] = "$field_name ($field_label)";
            }
        }

        return $file_fields;
    }

    /**
     * Fills a template with REDCap record data, and displays in 
     * editors for customization, before download.
     * 
     * Record id and template name passed via HTTP POST. Template variables are
     * replaced with record data, and returned in the editors rendered. User
     * can customize contents before downloading.
     * 
     * @see Template::fillTemplate() For filling template with REDCap record data.
     * @see CustomReporBuilder::initializeEditor() For initializing editors on page.
     * @since 3.0
     */
    public function generateFillTemplatePage()
    {   
        $rights = REDCap::getUserRights($this->userid);
        if ($rights[$this->userid]["data_export_tool"] === "0") 
        {
            exit("<div class='red'>You don't have premission to view this page</div><a href='" . $this->getUrl("index.php") . "'>Back to Front</a>");
        }
        
        $record = $_POST["participantID"][0];
        
        if (empty($record))
        {
            exit("<div class='red'>No record has been select. Please go back and select a record to fill the template.</div><a href='" . $this->getUrl("index.php") . "'>Back to Front</a>");
        }
        
        $template_filename = $_POST['template'];
        $template = new Template($this->templates_dir, $this->compiled_dir);

        try
        {
            $filled_template = $template->fillTemplate($template_filename, $record);
        
            $doc = new DOMDocument();
            $doc->loadHTML($filled_template);
        
            $header = $doc->getElementsByTagName("header")->item(0);
            $footer = $doc->getElementsByTagName("footer")->item(0);
            $main = $doc->getElementsByTagName("main")->item(0);
        
            $filled_main = $doc->saveHTML($main);
            $filled_header = empty($header) ? "" : $doc->saveHTML($header);
            $filled_footer = empty($footer)? "" : $doc->saveHTML($footer);
        }
        catch (Exception $e)
        {
            $errors[] = "<b>ERROR</b> [" . $e->getCode() . "] LINE [" . $e->getLine() . "] FILE [" . $e->getFile() . "] " . str_replace("Undefined index", "Field name does not exist", $e->getMessage());
        }
        ?>
        <link rel="stylesheet" href="<?php print $this->getUrl("app.css"); ?>" type="text/css">
        <div class="container"> 
            <div class="jumbotron">
                <div class="row">
                    <div class="col-md-10">
                        <h3>Download Template</h3>
                    </div>
                    <div class="col-md-2">
                        <a class="btn btn-primary" style="color:white" href="<?php print $this->getUrl("index.php");?>">Back to Front</a>
                    </div>
                </div>
                <hr/>
                <?php if (!empty($errors)) :?>
                    <div class="red container">
                        <h4>Error filling template! Please contact your REDCap administrator!</h4>
                        <p><a id="readmore-link" href="#">Click to view errors</a></p>
                        <div id="readmore" style="display:none">
                            <?php
                                foreach($errors as $error)
                                {
                                    print "<p>$error</p>";
                                }
                            ?>
                        </div>
                    </div>
                    <hr/>
                <?php endif;?>
                <div class="container syntax-rule">
                    <h4><u>Instructions</u></h4>
                    <p>You may download the report as is, or edit until you're satisfied, then download. You may also copy/paste the report into another editor and save, if you prefer a format other than PDF.</p>
                    <p><strong style="color:red">**IMPORTANT**</strong></p>
                    <ul>
                        <li>Tables and images may be cut off in PDF, because of size. If so, there is no current fix and you must edit your content until it fits. Some suggestions are to break up content into
                        multiple tables, shrink font, etc...</li>
                        <li>Any image uploaded to the plugin will be saved for future use by <strong>ALL</strong> users. <strong>Do not upload any identifying images.</strong></li>
                        <li>Calculations cannot be performed in the Template Engine, so raw values have been exported.</li>
                        <li>Fields in a repeatable event or instrument had their data pulled from the latest instance.</li>
                        <?php if ($rights[$user]["data_export_tool"] === "2") :?>
                            <li> Data has been de-identified according to user access rights</li>
                        <?php endif;?>
                    </ul>
                    <?php if (!$this->getProjectSetting("save-report-to-repo")) :?>
                        <div class="green" style="max-width: initial;">
                            <p>This module can save reports to the File Repository, upon download. This is currently <strong>enabled</strong> if you'd like to disable this contact your REDCap administrator.</p>
                        </div>
                    <?php else: ?>
                        <div class="red" style="max-width: initial;">
                            <p> This module can save reports to the File Repository,  upon download. This is currently <strong>disabled</strong>, but if you'd like to enable this contact your REDCap administrator.</p>
                        </div>
                    <?php endif;?>
                </div>
                <br/>
                <form action="<?php print $this->getUrl("DownloadFilledTemplate.php"); ?>" method="post">
                    <table class="table" style="width:100%;">
                        <tbody>
                            <tr>
                                <td style="width:25%;">Template Name <strong style="color:red">* Required</strong></td>
                                <td class="data">
                                    <div class="row">
                                        <div class="col-md-5">
                                            <input id="filename" name="filename" type="text" class="form-control" value="<?php print basename($template_filename, "_$this->pid.html") . " - $record";?>" required>
                                            <input name="record" type="hidden" value="<?php print $record;?>">
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td style="width:25%;">
                                    <p>Save report to A Field <strong style="color:black">(Optional)</strong></p>
                                    <p>
                                        This will save the report to a field in the record you chose. For repeating events/instruments it will be saved to the <b>latest instance</b>. 
                                        For longitudinal projects, if no event is chosen then the report is saved to the first event.
                                    </p>
                                    <p><b>WARNING:</b> This will override any previous documents saved to the field.</p>
                                </td>
                                <td class="data">
                                    <div class="row">
                                        <div class="col-md-5">
                                            <?php if (REDCap::isLongitudinal()) :?>
                                            <select id="save-report-to-event-dropdown" name="save-report-to-event-val" class="form-control selectpicker">
                                                <option value="">-Select an event-</option>
                                                <?php
                                                $events = REDCap::getEventNames(true, true);
                                                foreach($events as $event)
                                                {
                                                    print "<option value='$event'>$event</option>";
                                                } 
                                                ?>
                                            </select>
                                            <?php endif; ?>
                                            <select id="save-report-to-field-dropdown" name="save-report-to-field-val" class="form-control selectpicker">
                                                <option value="">-Select a field-</option>
                                                <?php
                                                $file_fields = $this->getAllFileFields();
                                                foreach($file_fields as $field => $label)
                                                {
                                                    print "<option value='$field'>$label</option>";
                                                } 
                                                ?>
                                            </select>
                                            <button id="save-report-btn" type="button" class="btn btn-primary" style="margin-top:25px">Save Report</button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="row" style="margin-bottom:20px">
                        <div class="col-md-2"><button id="download-pdf-btn" type="button" class="btn btn-primary">Download PDF</button></div>
                    </div>
                    <div class="collapsible-container">
                        <button type="button" class="collapsible">Add Header **Optional** <span class="fas fa-caret-down"></span><span class="fas fa-caret-up"></span></button>
                        <div class="collapsible-content">
                            <p>Anything in the header will appear at the top of every page in the template. <strong>If the header content is too big, it will overlap template data in the PDF.</strong></p>
                            <textarea cols="80" id="header-editor" name="header-editor" rows="10">
                                <?php print $filled_header;?>
                            </textarea>
                        </div>
                    </div>
                    <div class="collapsible-container">
                        <button type="button" class="collapsible">Add Footer **Optional** <span class="fas fa-caret-down"></span><span class="fas fa-caret-up"></span></button>
                        <div class="collapsible-content">
                            <p>Anything in the footer will appear at the bottom of every page in the template. <strong>If the footer content is too big, it will cuttoff in the PDF.</strong></p>
                            <textarea cols="80" id="footer-editor" name="footer-editor" rows="10">
                                <?php print $filled_footer;?>
                            </textarea>
                        </div>
                    </div>
                    <div style="margin-top:20px">
                        <textarea cols="80" id="editor" name="editor" rows="10">
                            <?php print $filled_main; ?>
                        </textarea>
                    </div>
                </form>
            </div>
        </div>
        <script src="<?php print $this->getUrl("vendor/ckeditor/ckeditor/ckeditor.js"); ?>"></script>
        <script src="<?php print $this->getUrl("scripts.js"); ?>"></script>
        <script>
            // JS to download PDF
            $("#download-pdf-btn").click(function () {
                // Updates the textarea elements that CKEDITOR replaces
                for (instance in CKEDITOR.instances)
                    CKEDITOR.instances[instance].updateElement();

                if ($("#editor").val() == "" || $("#filename").val() == "")
                {
                    alert("You need to enter a template name, AND something in the main editor to download.");
                }
                else
                {
                    $("form").submit();
                }
            });

            // JS to save report to field
            $("#save-report-btn").click(function () {
                // Remove previous sucess/failure message
                $("#save-report-status-msg").remove();

                // Updates the textarea elements that CKEDITOR replaces
                for (instance in CKEDITOR.instances)
                    CKEDITOR.instances[instance].updateElement();

                $.ajax({
                        url: "<?php print $this->getUrl("SaveFileToField.php"); ?>",
                        method: "POST",
                        data: $('form').serialize(),
                        success: function(data) {
                            try 
                            {
                                var json = JSON.parse(data);
                                if (json.success)
                                {
                                    $("#save-report-btn").after("<p id='save-report-status-msg' style='color:green'>Report was successfully saved!</p>");
                                }
                                else
                                {
                                    $("#save-report-btn").after("<p id='save-report-status-msg' style='color:red'>Failed to save report to field! " + json.error + "</p>");
                                }
                            }
                            catch(e)
                            {
                                console.log(e.message);
                                console.log(data);
                            }
                        }
                });
            });
        </script>
        <?php 
            print "<script>CKEDITOR.dtd.\$removeEmpty['p'] = true;</script>";
            $this->initializeEditor("header-editor", 200);
            $this->initializeEditor("footer-editor", 200);
            $this->initializeEditor("editor", 1000);
    }

    /**
     * Generates a page to create a template, or edit existing templates.
     * 
     * If a template name is given, then the function retrieves the contents of the template
     * for editing.
     * Else generate page with empty editors for creation. 
     * 
     * @see CustomTemplateEngine::checkPermissions() For checking if the user has permissions to view the page.
     * @param String $template   An existing template's name
     * @since 3.0
     */
    public function generateCreateEditTemplatePage($template = "")
    {
        if (!empty($template))
        {
            $this->checkPermissions();
            $template_name = $curr_template_name = $template;
            $template = file_get_contents($this->templates_dir . $template_name);

            $doc = new DOMDocument();
            $doc->loadHTML($template);

            $header = $doc->getElementsByTagName("header")->item(0);
            $footer = $doc->getElementsByTagName("footer")->item(0);
            $main = $doc->getElementsByTagName("main")->item(0);

            $main_data = $doc->saveHTML($main);
            $header_data = empty($header) ? "" : $doc->saveHTML($header);
            $footer_data = empty($footer)? "" : $doc->saveHTML($footer);
            $action = "edit";
        }
        else
        {
            $action = "create";
        }
        ?>
        <link rel="stylesheet" href="<?php print $this->getUrl("app.css"); ?>" type="text/css">
        <div class="container"> 
            <div class="jumbotron">
                <div class="row">
                    <div class="col-md-10">
                        <h3><?php print ucfirst($action); ?> Template</h3>
                    </div>
                    <div class="col-md-2">
                        <a class="btn btn-primary" style="color:white" href="<?php print $this->getUrl("index.php")?>">Back to Front</a>
                    </div>
                </div>
                <hr/>
                <div id="errors-container" style="display:none">
                    <div class="red container">
                        <h4>Template Validation Failed!</h4>
                        <p>Template was saved with the following errors. To discover where the error occured, match the line numbers in the error message to the ones in the Source view...</p>
                        <p><a id="readmore-link" href="#">Click to view errors</a></p>
                        <div id="readmore" style="display:none">
                            <p id="general-errors-header"><strong>General Errors...</strong></p>
                            <div id="general-errors"></div>
                            <p id="header-errors-header"><strong>Header Errors...</strong></p>
                            <div id="header-errors"></div>
                            <p id="footer-errors-header" ><strong>Footer Errors...</strong></p>
                            <div id="footer-errors"></div>
                            <p id="body-errors-header"><strong>Body Errors...</strong></p>
                            <div id="body-errors"></div>
                        </div>
                    </div>
                    <hr/>
                </div>
                <div class="container syntax-rule">
                    <h4><u>Instructions</u></h4>
                    <p>
                        Build your template in the WYSIWYG editor using the syntax guidelines below. Variables that you wish to pull must be contained in this template. 
                        You may format the template however you wish, including using tables.
                        <strong style="color:red"> 
                            When accessing fields in a repeatable event or instrument, this module will automatically pull data from the latest instance.
                        </strong>
                    </p>
                    <p>**The project id will be appended to the template name for identification purposes**</p>
                    <p><strong style="color:red">**IMPORTANT**</strong></p>
                    <ul>
                        <li>Any image uploaded to the plugin will be saved for future use by <strong>ALL</strong> users. <strong>Do not upload any identifying images.</strong></li>
                    </ul>
                </div>
                <h4><u>Syntax</u></h4>
                <div class="collapsible-container">
                    <button class="collapsible">Click to view syntax rules <span class="fas fa-caret-down"></span><span class="fas fa-caret-up"></span></button>
                    <div class="collapsible-content">
                        <p><strong style="color:red">**IMPORTANT**</strong></p>
                        <ul>
                            <li>'{' and '}' are special characters that should only be used to indicate the start and end of syntax</li>
                            <li>All HTML tags must wrap around syntax. i.e "<strong>&lt;strong&gt;</strong>{$redcap['variable']}<strong>&lt;/strong&gt;</strong>" is valid, "{$redcap<strong>&lt;strong&gt;</strong>['variable']}<strong>&lt;/strong&gt;</strong>" will throw an error</li>
                        </ul>
                        <div class="collapsible-container">
                            <button class="collapsible">Adding fields to your project: <strong>{$redcap['variable']}</strong> <span class="fas fa-caret-down"></span><span class="fas fa-caret-up"></span></button>
                            <div class="collapsible-content">
                                <div class="syntax-example">
                                    Example:
                                    <div>
                                        First Name: <strong>{$redcap['first_name']}</strong>, Last Name: <strong>{$redcap['last_name']}</strong>
                                    </div>
                                    Output:
                                    <div>
                                        First Name: <strong>John</strong>, Last Name: <strong>Smith</strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="collapsible-container">
                            <button class="collapsible">Adding events for longitudinal projects: <strong>{$redcap['event name']['variable']}</strong> <span class="fas fa-caret-down"></span><span class="fas fa-caret-up"></span></button>
                            <div class="collapsible-content">
                                <p><strong style="color:red">IMPORTANT:</strong> If an event is not indicated, it will default to the first event in a record's arm. You do not need to specify the event for classical projects.</p>
                                <div class="syntax-example">
                                    Example:
                                    <div>
                                        First Name: <strong>{$redcap['enrollment_arm_1']['first_name']}</strong>, Last Name: <strong>{$redcap['enrollment_arm_1']['last_name']}</strong>
                                    </div>
                                    Output:
                                    <div>
                                        First Name: <strong>John</strong>, Last Name: <strong>Smith</strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="collapsible-container">
                            <button class="collapsible">Show/Hide text and data using <strong>IF</strong> conditions: If something is true then display some text. <span class="fas fa-caret-down"></span><span class="fas fa-caret-up"></span></button>
                            <div class="collapsible-content">
                                <p><u>Syntax:</u><strong> {if $redcap['event name']['variable'] eq someValue}</strong> show this text <strong>{/if}</strong></p>
                                <div class="syntax-example">
                                    Example:
                                    <div>
                                        <strong>{if $redcap['enrollment_arm_1']['gender'] eq 'male'}</strong> The candidate sex is: {$redcap['enrollment_arm_1']['gender']} <strong>{/if}</strong>
                                    </div>
                                    Output if condition is true:
                                    <div>
                                        The candidate sex is <strong>male</strong>
                                    </div>
                                    Output if condition is false:
                                    <div>
                                        <span style="color:red">* If conditions are not met, no text is shown</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="collapsible-container">
                            <button class="collapsible"><strong>{if}</strong> conditions can be nested within one another to an infinite depth <span class="fas fa-caret-down"></span><span class="fas fa-caret-up"></span></button>
                            <div class="collapsible-content">
                                <p><u>Syntax:</u> <strong>{if $redcap['variable'] eq someValue}</strong> show <strong>{if $redcap['variable'] eq someValue}</strong> some <strong>{/if}</strong> text <strong>{/if}</strong></p>
                                <div class="syntax-example">
                                        Example:
                                        <div>
                                            <strong>{if $redcap['age'] gt 16}</strong> Consent: <strong>{if $redcap['consent'] eq 'Yes'}</strong> Yes <strong>{/if}</strong> <strong>{/if}</strong>
                                        </div>
                                        Output:
                                        <br/>
                                        &emsp;<u>Case 1</u> $redcap['age'] eq 18 and $redcap['consent'] eq 'Yes'
                                        <div>
                                            Consent: Yes
                                        </div>
                                        &emsp;<u>Case 2</u> $redcap['age'] eq 18 and $redcap['consent'] eq 'No'
                                        <div>
                                            Consent: <span style="color: red">* The text "Yes" is not shown</span>
                                        </div>
                                        &emsp;<u>Case 3</u> $redcap['age'] eq 10 and $redcap['consent'] eq 'No'
                                        <div>
                                            <span style="color: red">* No text is shown</span>
                                        </div>
                                </div>
                            </div>
                        </div>
                        <div class="collapsible-container">
                            <button class="collapsible"><strong>{if}</strong> conditions can be chained with <strong>{elseif}</strong> <span class="fas fa-caret-down"></span><span class="fas fa-caret-up"></span></button>
                            <div class="collapsible-content">
                                <p><u>Syntax:</u> <strong>{if $redcap['variable'] eq someValue}</strong> show <strong>{elseif $redcap['variable'] eq someValue}</strong> some text <strong>{/if}</strong></p>
                                <div class="syntax-example">
                                        Example:
                                        <div>
                                            <strong>Gender: {if $redcap['gender'] eq 'male'}</strong> Male <strong>{elseif $redcap['gender'] eq 'female'}</strong> Female <strong>{/if}</strong>
                                        </div>
                                        Output:
                                        <br/>
                                        &emsp;<u>Case 1</u> $redcap['gender'] eq 'Male'
                                        <div>
                                            Gender: Male
                                        </div>
                                        &emsp;<u>Case 2</u> $redcap['gender'] eq 'Female'
                                        <div>
                                            Gender: Female
                                        </div>
                                        &emsp;<u>Case 3</u> $redcap['gender'] neq 'Male' and $redcap['gender'] neq 'Female'
                                        <div>
                                            <span style="color: red">* No text is shown</span>
                                        </div>
                                </div>
                            </div>
                        </div>
                        <div class="collapsible-container">
                            <button class="collapsible">A default <strong>{if}</strong> condition can be used with <strong>{else}</strong> <span class="fas fa-caret-down"></span><span class="fas fa-caret-up"></span></button>
                            <div class="collapsible-content">
                                <p><strong style="color:red">IMPORTANT:</strong> Each {if} can have <strong>at most</strong> one {else} clause, and must be the last clause in the statement.</p>
                                <p><u>Syntax:</u> <strong>{if $redcap['variable'] eq someValue}</strong> show <strong>{else}</strong> some text <strong>{/if}</strong></p>
                                <div class="syntax-example">
                                        Example:
                                        <div>
                                            <strong>Gender: {if $redcap['gender'] eq 'male'}</strong> Male <strong>{else}</strong> Female <strong>{/if}</strong>
                                        </div>
                                        Output:
                                        <br/>
                                        &emsp;<u>Case 1</u> $redcap['gender'] eq 'Male'
                                        <div>
                                            Gender: Male
                                        </div>
                                        &emsp;<u>Case 2</u> $redcap['gender'] neq 'Male'
                                        <div>
                                            Gender: Female
                                        </div>
                                </div>
                            </div>
                        </div>
                        <div class="collapsible-container">
                            <button class="collapsible">Use a combination of comparison operators to build more complex syntax. <span class="fas fa-caret-down"></span><span class="fas fa-caret-up"></span></button>
                            <div class="collapsible-content">
                                <p><u>Logical Quantifiers</u></p>
                                <table border="1">
                                    <colgroup>
                                        <col align="center" class="alternates">
                                        <col class="meaning">
                                        <col class="example">
                                    </colgroup>
                                    <thead><tr>
                                        <th align="center">Qualifier</th>
                                        <th>Syntax Example</th>
                                        <th>Meaning</th>
                                    </tr></thead>
                                    <tbody>
                                        <tr>
                                            <td align="center">eq</td>
                                            <td>$a eq $b</td>
                                            <td>equals</td>
                                        </tr>
                                        <tr>
                                            <td align="center">ne, neq</td>
                                            <td>$a neq $b</td>
                                            <td>not equals</td>
                                        </tr>
                                        <tr>
                                            <td align="center">gt</td>
                                            <td>$a gt $b</td>
                                            <td>greater than</td>
                                        </tr>
                                        <tr>
                                            <td align="center">lt</td>
                                            <td>$a lt $b</td>
                                            <td>less than</td>
                                        </tr>
                                        <tr>
                                            <td align="center">gte, ge</td>
                                            <td>$a ge $b</td>
                                            <td>greater than or equal</td>
                                        </tr>
                                        <tr>
                                            <td align="center">lte, le</td>
                                            <td>$a le $b</td>
                                            <td>less than or equal</td>
                                        </tr>
                                        <tr>
                                            <td align="center">not</td>
                                            <td>not $a</td>
                                            <td>negation (unary)</td>
                                        </tr>
                                    </tbody>
                                </table>
                                <p><u>Syntax:</u> <strong>{if $redcap['event name']['variable'] eq someValue}</strong> show this text <strong>{/if}</strong></p>
                                <div class="syntax-example">
                                    Example:
                                    <div>
                                        <strong>{if $redcap['enrollment_arm_1']['gender'] eq 'male'}</strong> The candidate sex is: {$redcap['enrollment_arm_1']['gender']} <strong>{/if}</strong>
                                    </div>
                                    Output:
                                    <br/>
                                    &emsp;<u>Case 1:</u> $redcap['enrollment_arm_1']['gender'] eq 'male'
                                    <div>
                                        The candidate sex is male
                                    </div>
                                    &emsp;<u>Case 3:</u> $redcap['enrollment_arm_1']['gender'] neq 'male'
                                    <div>
                                        <span style="color:red">* No text is shown</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="collapsible-container">
                            <button class="collapsible">Chain multiple expressions in the same if statement using the logical operators <strong>or</strong> & <strong>and</strong> <span class="fas fa-caret-down"></span><span class="fas fa-caret-up"></span></button>
                            <div class="collapsible-content">
                                <p><u>Logical Quantifiers</u></p>
                                <table border="1">
                                    <colgroup>
                                        <col align="center" class="alternates">
                                        <col class="meaning">
                                        <col class="example">
                                    </colgroup>
                                    <thead><tr>
                                        <th align="center">Qualifier</th>
                                        <th>Syntax Example</th>
                                        <th>Meaning</th>
                                    </tr></thead>
                                    <tbody>
                                        <tr>
                                            <td align="center">and</td>
                                            <td>$a and $b</td>
                                            <td>both $a and $b must be true, $a and $b can be expressions i.e. $a = ($c gt $d)</td>
                                        </tr>
                                        <tr>
                                            <td align="center">or</td>
                                            <td>$a or $b</td>
                                            <td>either $a or $b can be true, $a and $b can be expressions i.e. $a = ($c gt $d)</td>
                                        </tr>
                                    </tbody>
                                </table>
                                <p><u>Note:</u> Use parenthesis '(' and ')' to group expressions.</p>
                                <p>
                                    <u>On operator precedance:</u> 
                                    <strong>and</strong> takes precedance before <strong>or</strong>, therefore 
                                    <strong>"$redcap['enrollment_arm_1']['gender'] eq 'male' or $redcap['enrollment_arm_1']['gender'] eq 'female' and $redcap['enrollment_arm_1']['age'] gt '10'"</strong>
                                    will parse <strong>"$redcap['enrollment_arm_1']['gender'] eq 'female' and $redcap['enrollment_arm_1']['age'] gt '10'"</strong> first. To control the order of precedence, use parenthesis.
                                </p>
                                <p><u>Syntax:</u> <strong>{if ($redcap['event name']['variable'] eq someValue) or ($redcap['event name']['variable'] eq someValue2)}</strong> show this text <strong>{/if}</strong></p>
                                <div class="syntax-example">
                                    Example:
                                    <div>
                                        <strong>{if ($redcap['enrollment_arm_1']['gender'] eq 'male') or ($redcap['dosage_arm_1']['dosage'] gt 10)}</strong> The candidate sex is: {$redcap['enrollment_arm_1']['gender']} <strong>{/if}</strong>
                                    </div>
                                    Output:
                                    <br/>
                                    &emsp;<u>Case 1:</u> $redcap['enrollment_arm_1']['gender'] eq 'male'
                                    <div>
                                        The candidate sex is male
                                    </div>
                                    &emsp;<u>Case 2:</u> $redcap['dosage_arm_1']['dosage'] gt 10
                                    <div>
                                        The candidate sex is male
                                    </div>
                                    &emsp;<u>Case 3:</u> $redcap['enrollment_arm_1']['gender'] eq 'female' and $redcap['dosage_arm_1']['dosage'] lte 10
                                    <div>
                                        <span style="color:red">* No text is shown</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="collapsible-container">
                            <button class="collapsible">Query checkbox/matrix values using <strong>in_array</strong> <span class="fas fa-caret-down"></span><span class="fas fa-caret-up"></span></button>
                            <div class="collapsible-content">
                                <p><u>Syntax:</u> <strong>{if in_array('someValue', $redcap['variable'])}</strong> show this text <strong>{/if}</strong></p>
                                <div class="syntax-example">
                                    Example:
                                    <div>
                                        <strong>{if in_array('Monday', $redcap['weekdays'])}</strong> The day of the week is Monday <strong>{/if}</strong>
                                    </div>
                                    Output:
                                    <br/>
                                    &emsp;<u>Case 1:</u> $redcap['weekdays'] contains 'Monday'
                                    <div>
                                        The day of the week is Monday
                                    </div>
                                    &emsp;<u>Case 2:</u> $redcap['weekdays'] doesn't contain 'Monday'
                                    <div>
                                        <span style="color:red">* No text is shown</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="collapsible-container">
                            <button class="collapsible">Print all checkbox/matrix values using <strong>{$redcap['variable']['allValues']}</strong> <span class="fas fa-caret-down"></span><span class="fas fa-caret-up"></span></button>
                            <div class="collapsible-content">
                                <div class="syntax-example">
                                    Example:
                                    <div>
                                        All options: <strong>{$redcap['options']['allValues']}</strong>
                                    </div>
                                    Output:
                                    <br/>
                                    <div>
                                    All options: <strong>None of the above, All of the above, A and C</strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="collapsible-container">
                            <button class="collapsible">Hide/show a table and all its contents by <strong>nesting the table inside an {if} condition.</strong> <span class="fas fa-caret-down"></span><span class="fas fa-caret-up"></span></button>
                            <div class="collapsible-content">
                                <u>Syntax:</u> <strong>{if $redcap['variable'] eq someValue}</strong><table><tbody><tr><td>Text 1</td><td>Text 2</td></tr></tbody></table><strong>{/if}</strong>
                                <br/><br/>
                                <div class="syntax-example">
                                    Example:
                                    <div>
                                        <strong>{if $redcap['gender'] eq 'male'}</strong>
                                        <table><tbody><tr><td>{$redcap['gender']}</td><td>{$redcap['gender']}</td></tr></tbody></table>
                                        <strong>{/if}</strong>
                                    </div>
                                    Output:
                                    <br/>
                                    &emsp;<u>Case 1:</u> $redcap['gender'] eq 'male'
                                    <div>
                                        <table><tbody><tr><td>male</td><td>12</td></tr></tbody></table>
                                    </div>
                                    &emsp;<u>Case 2:</u> $redcap['gender'] eq 'female'
                                    <div>
                                        <span style="color:red">* If conditions are not met, no text is shown</span>
                                    </div>
                                </div>
                                <br/>
                                <u>NOTE:</u> If you want to hide sections of a table, place the beginning and end of the if-statements in separate rows.
                                <br/><br/>
                                <div class="syntax-example">
                                    Example:
                                    <div>
                                        <table>
                                            <tbody>
                                                <tr><td>Text 1</td><td>Text 2</td></tr>
                                                <tr><td colspan="2"><strong>{if $redcap['gender'] eq 'male'}</strong></td></tr>
                                                <tr><td>{$redcap['gender']}</td><td>{$redcap['gender']}</td></tr>
                                                <tr><td colspan="2"><strong>{/if}</strong></td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    Output:
                                    <br/>
                                    &emsp;<u>Case 1:</u> $redcap['gender'] eq 'male'
                                    <div>
                                        <table><tbody><tr><td>Text 1</td><td>Text 2</td></tr><tr><td>male</td><td>12</td></tr></tbody></table>
                                    </div>
                                    &emsp;<u>Case 2:</u> $redcap['gender'] eq 'female'
                                    <div>
                                        <table><tbody><tr><td>Text 1</td><td>Text 2</td></tr></tbody></table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="collapsible-container">
                            <button class="collapsible">Hide/show a row in a table by adding <strong>$showLabelAndRow</strong> to the <strong>{if}</strong> condition that determines if the row should be shown or hidden. <span class="fas fa-caret-down"></span><span class="fas fa-caret-up"></span></button>
                            <div class="collapsible-content">
                                <u>Syntax:</u> <table><tbody><tr><td>{if $redcap['variable'] eq someValue and <strong>$showLabelAndRow</strong>} Text 1</td><td>Text 2 {/if}</td></tr></tbody></table>
                                <br/><br/>
                                <div class="syntax-example">
                                        Example:
                                        <div>
                                            <table>
                                                <tbody>
                                                    <tr><td>{if $redcap['age'] gt 20 and <strong>$showLabelAndRow</strong>} Age</td><td>{$redcap['age']} {/if}</td></tr>
                                                    <tr><td>Admitted</td><td>Yes</td></tr>
                                                </tbody>
                                            </table>
                                        </div>
                                        Output:
                                        <br/>
                                        &emsp;<u>Case 1:</u> $redcap['age'] eq 30
                                        <div>
                                            <table>
                                                <tbody>
                                                    <tr><td>Age</td><td>30</td></tr>
                                                    <tr><td>Admitted</td><td>Yes</td></tr>
                                                </tbody>
                                            </table>
                                        </div>
                                        &emsp;<u>Case 2:</u> $redcap['age'] eq 18
                                        <div>
                                            <table>
                                                <tbody>
                                                    <tr><td>Admitted</td><td>Yes</td></tr>
                                                </tbody>
                                            </table>
                                            <br/>
                                            <span style="color:red">* The age row isn't shown</span>
                                        </div>
                                </div>
                            </div>
                        </div>
                        <div class="collapsible-container">
                            <button class="collapsible">Escape quotes using <strong>\</strong>. <span class="fas fa-caret-down"></span><span class="fas fa-caret-up"></span></button>
                            <div class="collapsible-content">
                                <p><strong style="color:red">IMPORTANT:</strong> Quotes that appear within quotes must be escaped, otherwise the template will not run.</p>
                                <div class="syntax-example">
                                    Example:
                                    <div>
                                    {if $redcap['enrollment_arm_1']['institute'] eq 'BC Children<strong style="color:red">\'</strong>s Hospital Research Institute'} The candidate works at the institute. {/if}
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="collapsible-container">
                            <button class="collapsible">Add page breaks in template. <span class="fas fa-caret-down"></span><span class="fas fa-caret-up"></span></button>
                            <div class="collapsible-content">
                                <p><strong style="color:red">IMPORTANT:</strong> When using page breaks, they must never be attached to an if condition, otherwise the temple will have rendering errors</p>
                                <u>Syntax:</u> In the Source view of the editor find the element you want to add a page break before and add <b>style="page-break-before:always"</b>
                                <br/><br/>
                                <div class="syntax-example">
                                    Example:
                                    <div>
                                    <?php print htmlspecialchars("<h1 ") . "<b>style=\"page-break-before:always\"</b>" . htmlspecialchars(">Add a page break before this header</h1>"); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="collapsible-container">
                            <button class="collapsible">Add special characters in template. <span class="fas fa-caret-down"></span><span class="fas fa-caret-up"></span></button>
                            <div class="collapsible-content">
                                <p>
                                    <strong style="color:red">IMPORTANT:</strong> When using special characters you <b>MUST</b> use "DejaVu Sans, sans-serif" as your font. The module only supports characters from the Windows ANSI encoding.
                                    See <a href="https://www.w3schools.com/charsets/ref_html_ansi.asp"><u>here</u></a> for a list of allowable characters. 
                                </p>
                                <u>Syntax:</u> In the Source view of the editor find the entity code for the character you want to use and paste it.</b>
                                <br/><br/>
                                <div class="syntax-example">
                                    Example:
                                    <p>In source view:</p>
                                    <div>&amp;copy;</div>
                                    <p>In editor:</p>
                                    <div>&copy;</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <h4><u>Fields & Events</u></h4>
                <?php if (REDCap::isLongitudinal()): ?>
                    <div class="collapsible-container">
                        <button class="collapsible">Events <span class="fas fa-caret-down"></span><span class="fas fa-caret-up"></span></button>
                        <div class="collapsible-content">
                        <p><u>NOTE:</u> Events come preformatted for ease of use. Users will have to replace 'field' with the REDCap field they'd like to use.</p>
                        <?php 
                            $events = REDCap::getEventNames(TRUE);
                            foreach ($events as $event)
                            {
                                print "<p><strong>$event</strong> -> {\$redcap['$event'][field]}</p>";
                            }
                        ?>
                        </div>
                    </div>
                <?php endif; ?>
                <div class="collapsible-container">
                    <button class="collapsible">Click to view fields <span class="fas fa-caret-down"></span><span class="fas fa-caret-up"></span></button>
                    <div class="collapsible-content">
                    <p>
                        <?php if (REDCap::isLongitudinal() && $this->Proj->project['surveys_enabled']): ?>
                            <p><u>NOTE:</u> Fields are sorted by their instruments, and are preformatted for ease of use. For Longitudinal projects, this sytnax will default to the first event in a record's arm.
                            To access other events please append their name before the field (<i>See adding events for longitdinal projects, under syntax rules</i>).</p>
                            <p>Survey completion timestamps can be pulled, and proper formatting for enabled forms are at the bottom.</p>
                        <?php elseif (REDCap::isLongitudinal()): ?>
                            <u>NOTE:</u> Fields are sorted by their instruments, and are preformatted for ease of use. For Longitudinal projects, this sytnax will default to the first event in a record's arm.
                            To access other events please append their name before the field (<i>See adding events for longitdinal projects, under syntax rules</i>).
                        <?php elseif ($this->Proj->project['surveys_enabled']): ?> 
                            <p><u>NOTE:</u> Fields are sorted by their instruments, and are preformatted for ease of use.</p>
                            <p>Survey completion timestamps can be pulled, and proper formatting for enabled forms are at the bottom.</p>
                        <?php else: ?>
                            <u>NOTE:</u> Fields are sorted by their instruments, and are preformatted for ease of use.
                        <?php endif;?>
                    </p>
                    <?php
                        $instruments = REDCap::getInstrumentNames();
                        foreach ($instruments as $unique_name => $label)
                        {
                            print "<div class='collapsible-container'><button class='collapsible'>$label <span class='fas fa-caret-down'></span><span class='fas fa-caret-up'></span></button>";
                            $fields = REDCap::getDataDictionary("array", FALSE, null, array($unique_name));
                            print "<div class='collapsible-content'>";
                            foreach ($fields as $index => $field)
                            {
                                if ($field["field_type"] !== "descriptive")
                                {
                                    print "<p><strong>$index</strong> -> {\$redcap['$index']}</p>";
                                        
                                    if (!empty($field["select_choices_or_calculations"]) && $field["field_type"] !== "calc")
                                    {
                                        $valuesAndLabels = explode("|", $field["select_choices_or_calculations"]);
                                        if ($field["field_type"] === "slider")
                                        {
                                            $labels = $valuesAndLabels;
                                        }
                                        else
                                        {
                                            $labels = array();
                                            foreach($valuesAndLabels as $pair)
                                            {
                                                array_push($labels, substr($pair, strpos($pair, ",")+1));
                                            }
                                        }

                                        if (sizeof($labels) > 0)
                                        {
                                            print "<div style='padding-left:20px'><u>OPTIONS</u>: ";
                                            foreach($labels as $label)
                                            {
                                                print "\"" . trim(strip_tags($label)) . "\", ";
                                            }
                                            print "</div>";
                                        }
                                    }
                                }
                            }
                            print "<p><strong>{$unique_name}_complete</strong> -> {\$redcap['{$unique_name}_complete']}</p>";
                            print "<div style='padding-left:20px'><u>OPTIONS</u>: \"Complete\", \"Incomplete\", \"Unverified\"</div>";
                            print "</div></div>";
                        }
                        ?>
                        <?php if ($this->Proj->project['surveys_enabled']): ?>
                        <div class='collapsible-container'>
                            <button class='collapsible'>Survey Completion Timestamps <span class='fas fa-caret-down'></span><span class='fas fa-caret-up'></span></button>
                            <div class='collapsible-content'>
                                <?php
                                    foreach ($instruments as $unique_name => $label)
                                    {
                                        if (!empty($this->Proj->forms[$unique_name]['survey_id']))
                                        {
                                            print "<p><strong>$label</strong> -> {\$redcap['{$unique_name}_timestamp']}</p>";
                                        }
                                    }
                                ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <br/><br/>
                <form>
                    <table class="table" style="width:100%;">
                        <tbody>
                            <tr>
                                <td style="width:25%;">Template Name <strong style="color:red">*Required</strong></td>
                                <td class="data">
                                    <div class="col-md-5">
                                        <input id="templateName" name="templateName" type="text" class="form-control" value="<?php print str_replace(array("_$this->pid", " - INVALID", ".html"), "", $template_name); ?>">
                                        <input id="action" type="hidden" name="action" value="<?php print $action;?>">
                                        <input id="currTemplateName" name="currTemplateName" type="hidden" value="<?php print $curr_template_name; ?>">
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td style="width:25%;"><button id="save-template-btn" type="button" class="btn btn-primary">Save Template</button></td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="collapsible-container">
                        <button type="button" class="collapsible">Add Header **Optional** <span class="fas fa-caret-down"></span><span class="fas fa-caret-up"></span></button>
                        <div class="collapsible-content"> 
                            <p>Anything in the header will appear at the top of every page in the template. All syntax rules apply. <strong>If the header content is too big, it will overlap template data in the PDF.</strong></p>
                            <textarea cols="80" id="headerEditor" name="header-editor" rows="10">
                                <?php print $header_data; ?>
                            </textarea>
                        </div>
                    </div>
                    <div class="collapsible-container">
                        <button type="button" class="collapsible">Add Footer **Optional** <span class="fas fa-caret-down"></span><span class="fas fa-caret-up"></span></button>
                        <div class="collapsible-content">
                            <p>Anything in the footer will appear at the bottom of every page in the template. All syntax rules apply. <strong>If the footer content is too big, it will cutoff in the PDF.</strong></p>
                            <textarea cols="80" id="footerEditor" name="footer-editor" rows="10">
                                <?php print $footer_data; ?>
                            </textarea>
                        </div>
                    </div>
                    <div style="margin-top:20px">
                        <textarea cols="80" id="editor" name="editor" rows="10">
                            <?php print $main_data; ?>
                        </textarea>
                    </div>
                </form>
            </div>
        </div>
        <script src="<?php print $this->getUrl("vendor/ckeditor/ckeditor/ckeditor.js"); ?>"></script>
        <script src="<?php print $this->getUrl("scripts.js"); ?>"></script>
        <script>
            $("#save-template-btn").click(function () {
                // Updates the textarea elements that CKEDITOR replaces
                CKEDITOR.instances.headerEditor.updateElement();
                CKEDITOR.instances.footerEditor.updateElement();
                CKEDITOR.instances.editor.updateElement();

                if ($("#editor").val() == "" || $("#templateName").val() == "")
                {
                    alert("You need to enter a template name, AND something in the main editor to save.");
                }
                else
                {
                    $.ajax({
                        url: "<?php print $this->getUrl("SaveTemplate.php"); ?>",
                        method: "POST",
                        data: $('form').serialize(),
                        success: function(data) {
                            var json = JSON.parse(data);
                            if (json.errors)
                            {
                                var toAppend;

                                if (json.errors.otherErrors) {
                                    json.errors.otherErrors.forEach(function(item) {
                                        toAppend += "<p>" + item + "</p>";
                                    })
                                    $("#general-errors-header").show();
                                    $("#general-errors").empty().append(toAppend).show();
                                }
                                else
                                {
                                    $("#general-errors-header").hide();
                                    $("#general-errors").hide()
                                }

                                if (json.errors.headerErrors) {
                                    toAppend = "";
                                    json.errors.headerErrors.forEach(function(item) {
                                        toAppend += "<p>" + item + "</p>";
                                    })
                                    $("#header-errors-header").show();
                                    $("#header-errors").empty().append(toAppend).show();
                                }
                                else
                                {
                                    $("#header-errors-header").hide();
                                    $("#header-errors").hide()
                                }

                                if (json.errors.footerErrors) {
                                    toAppend = "";
                                    json.errors.footerErrors.forEach(function(item) {
                                        toAppend += "<p>" + item + "</p>";
                                    })
                                    $("#footer-errors-header").show();
                                    $("#footer-errors").empty().append(toAppend).show();
                                }
                                else
                                {
                                    $("#footer-errors-header").hide();
                                    $("#footer-errors").hide()
                                }
                                
                                if (json.errors.templateErrors) {
                                    toAppend = "";
                                    json.errors.templateErrors.forEach(function(item) {
                                        toAppend += "<p>" + item + "</p>";
                                    })
                                    $("#body-errors-header").show();
                                    $("#body-errors").empty().append(toAppend).show();
                                }
                                else
                                {
                                    $("#body-errors-header").hide();
                                    $("#body-errors").hide()
                                }

                                $("#currTemplateName").val(json.currTemplateName);
                                $("#action").val("edit");
                                $("#errors-container").show();
                                window.scroll(0,0); // Errors are at top of page
                            }
                            else
                            {
                                // Go to homepage
                                window.location.href = json.redirect;
                            }
                        }
                    });
                };
            });
        </script>
        <?php
        $this->initializeEditor("headerEditor", 200);
        $this->initializeEditor("footerEditor", 200);
        $this->initializeEditor("editor", 1000);
    }

    /**
     * Obtain custom record label & secondary unique field labels for ALL records.
	 * Limit by array of record names. If provide $records parameter as a single record string, then return string (not array).
	 * Return array with record name as key and label as value.
	 * If $arm == 'all', then get labels for the first event in EVERY arm (assuming multiple arms),
	 * and also return
     * 
     * Copied from the identical function in REDCap's Records class.
     * 
     * @since 3.1
     */
    private function getCustomRecordLabelsSecondaryFieldAllRecords($records=array(), $removeHtml=false, $arm=null, $boldSecondaryPkValue=false, $cssClass='crl')
	{
		global $secondary_pk, $custom_record_label, $Proj;
		// Determine which arm to pull these values for
		if ($arm == 'all' && $Proj->longitudinal && $Proj->multiple_arms) {
			// If project has more than one arm, then get first event_id of each arm
			$event_ids = array();
			foreach (array_keys($Proj->events) as $this_arm) {
				$event_ids[] = $Proj->getFirstEventIdArm($this_arm);
			}
		} else {
			// Get arm
			if ($arm === null) $arm = getArm();
			// Get event_id of first event of the given arm
			$event_ids = array($Proj->getFirstEventIdArm(is_numeric($arm) ? $arm : getArm()));
		}
		// Place all records/labels in array
		$extra_record_labels = array();
		// If $records is a string, then convert to array
		$singleRecordName = null;
		if (!is_array($records)) {
			$singleRecordName = $records;
			$records = array($records);
		}
		// Set flag to limit records
		$limitRecords = !empty($records);
		// Customize the Record ID pulldown menus using the SECONDARY_PK appended on end, if set.
		if ($secondary_pk != '')
		{
			// Get validation type of secondary unique field
			$val_type = $Proj->metadata[$secondary_pk]['element_validation_type'];
			$convert_date_format = (substr($val_type, 0, 5) == 'date_' && (substr($val_type, -4) == '_mdy' || substr($val_type, -4) == '_mdy'));
			// Set secondary PK field label
			$secondary_pk_label = $Proj->metadata[$secondary_pk]['element_label'];
			// PIPING: Obtain saved data for all piping receivers used in secondary PK label
			if (strpos($secondary_pk_label, '[') !== false && strpos($secondary_pk_label, ']') !== false) {
				// Get fields in the label
				$secondary_pk_label_fields = array_keys(getBracketedFields($secondary_pk_label, true, true, true));
				// If has at least one field piped in the label, then get all the data for these fields and insert one at a time below
				if (!empty($secondary_pk_label_fields)) {
					$piping_record_data = Records::getData('array', $records, $secondary_pk_label_fields, $event_ids);
				}
			}
            // Get back-end data for the secondary PK field
            $query = $this->framework->createQuery();
            $query->add("select record, event_id, value from redcap_data where project_id = ? and field_name = ?", [$this->pid, $secondary_pk]);
            $query->add("and")->addInClause("event_id", $event_ids);

			if ($limitRecords) {
                $query->add("and")->addInClause("record", $records);
            }
            
			$q = $query->execute();
			while ($row = $q->fetch_assoc())
			{
				// Set the label for this loop (label may be different if using piping in it)
				if (isset($piping_record_data)) {
					// Piping: pipe record data into label for each record
					$this_secondary_pk_label = Piping::replaceVariablesInLabel($secondary_pk_label, $row['record'], $event_ids, 1, $piping_record_data);
				} else {
					// Static label for all records
					$this_secondary_pk_label = $secondary_pk_label;
				}
				// If the secondary unique field is a date/time field in MDY or DMY format, then convert to that format
				if ($convert_date_format) {
					$row['value'] = DateTimeRC::datetimeConvert($row['value'], 'ymd', substr($val_type, -3));
				}
				// Set text value
				$this_string = "(" . remBr($this_secondary_pk_label . " " .
							   ($boldSecondaryPkValue ? "<b>" : "") .
							   decode_filter_tags($row['value'])) .
							   ($boldSecondaryPkValue ? "</b>" : "") .
							   ")";
				// Add HTML around string (unless specified otherwise)
				$extra_record_labels[$Proj->eventInfo[$row['event_id']]['arm_num']][$row['record']] = ($removeHtml) ? $this_string : RCView::span(array('class'=>$cssClass), $this_string);
			}
		}
		// [Retrieval of ALL records] If Custom Record Label is specified (such as "[last_name], [first_name]"), then parse and display
		// ONLY get data from FIRST EVENT
		if (!empty($custom_record_label))
		{
			// Loop through each event (will only be one UNLESS we are attempting to get label for multiple arms)
			$customRecordLabelsArm = array();
			foreach ($event_ids as $this_event_id) {
				$customRecordLabels = getCustomRecordLabels($custom_record_label, $this_event_id, ($singleRecordName ? $records[0]: null));
				if (!is_array($customRecordLabels)) $customRecordLabels = array($records[0]=>$customRecordLabels);
				$customRecordLabelsArm[$Proj->eventInfo[$this_event_id]['arm_num']] = $customRecordLabels;
			}
			foreach ($customRecordLabelsArm as $this_arm=>&$customRecordLabels)
			{
				foreach ($customRecordLabels as $this_record=>$this_custom_record_label)
				{
					// If limiting by records, ignore if not in $records array
					if ($limitRecords && !in_array($this_record, $records)) continue;
					// Set text value
					$this_string = remBr(decode_filter_tags($this_custom_record_label));
					// Add initial space OR add placeholder
					if (isset($extra_record_labels[$this_arm][$this_record])) {
						$extra_record_labels[$this_arm][$this_record] .= ' ';
					} else {
						$extra_record_labels[$this_arm][$this_record] = '';
					}
					// Add HTML around string (unless specified otherwise)
					$extra_record_labels[$this_arm][$this_record] .= ($removeHtml) ? $this_string : RCView::span(array('class'=>$cssClass), $this_string);
				}
			}
			unset($customRecordLabels);
		}
		// If we're not collecting multiple arms here, then remove arm key
		if ($arm != 'all') {
			$extra_record_labels = array_shift($extra_record_labels);
		}
		// Return string (single record only)
		if ($singleRecordName != null) {
			return (isset($extra_record_labels[$singleRecordName])) ? $extra_record_labels[$singleRecordName] : '';
		} else {
			// Return array
			return $extra_record_labels;
		}
	}

    /**
     * For each record in $records, generate a label that contains the record id and all secondary ids.
     * 
     * @param Array $records   Array or records.
     * @since 3.0
     */
    function getDropdownOptions($filter = false)
    {
        $rights = REDCap::getUserRights($this->userid);
        $id_field = REDCap::getRecordIdField();
        $records = json_decode(REDCap::getData("json", null, array($id_field), null, $rights[$this->userid]["group_id"]), true);

        if ($filter) 
        {
            $log_event_table = method_exists('\REDCap', 'getLogEventTable') ? REDCap::getLogEventTable($this->pid) : "redcap_log_event";

            // In case records have been deleted, and a new record assigned the previous ID, such as for auto-numbering.
            $query = $this->framework->createQuery();
            $query->add("select pk, ts from $log_event_table where event = 'DELETE' and project_id = ? order by ts asc", [$this->pid]);
            $result = $query->execute();

            while ($row = $result->fetch_assoc()) {
                $deleted[$row["pk"]] = $row["ts"];
            }

            $query = $this->framework->createQuery();
            $query->add("SELECT pk, max(ts) as ts FROM $log_event_table where (description = 'Custom Template Engine - Downloaded Report' or description = 'Custom Template Engine - Downloaded Reports' or description = 'Downloaded Report' or description = 'Downloaded Reports') and page = 'ExternalModules/index.php' and pk is not null and project_id = ? group by pk order by ts asc", [$this->pid]);
            $result = $query->execute();

            while ($row = $result->fetch_assoc()) {
                $pk = $row["pk"];
                if (strpos($pk, ",") != FALSE) {
                    $pk = explode(",", $pk);
                    $pk =  array_map("trim", $pk);
                    foreach($pk as $record) {
                        $printed[$record] = $row["ts"];
                    }
                }
                else
                {
                    $printed[$pk] = $row["ts"];
                }
            }

            foreach($printed as $record => $ts) {
                if ($deleted[$record] && $deleted[$record] > $ts) { // Skip records that have ben deleted
                    continue;
                }
                $previously_printed[] = $record;
            }

            // Must apply array_unique in case record has been printed more than once
            $previously_printed = array_unique($previously_printed);
        }

        $custom_labels = $this->getCustomRecordLabelsSecondaryFieldAllRecords(array_column($records, $id_field), true, "all", false, "");
        foreach($records as $record)
        {
            $to_add = $record[$id_field];
            $previously_printed = (is_null($previously_printed)) ? [] : $previously_printed; // LS for PHP 8
            $participant_options = (is_null($participant_options)) ? [] : $participant_options; // LS for PHP 8
            if (!in_array($to_add, $previously_printed)) 
            {
                if (!in_array($to_add, array_keys($participant_options), true))
                {
                    $arm_num = REDCap::isLongitudinal() ? array_pop(explode("arm_", $record["redcap_event_name"])) : "1";;
                    $label = $custom_labels[$arm_num][$to_add]; 
                    if (!empty($label))
                    {
                        $participant_options[$to_add] = "$to_add $label";
                    }
                    else
                    {
                        $participant_options[$to_add] = $to_add;
                    }
                }
            }
        }
        return $participant_options;
    } 

    /**
     * Generate landing page of module, and initialize modules folders.
     * 
     * Retrieves records in project, and existing project templates from server. REDCap records will
     * display with any secondary and custom labels. From the landing page, a user can fill, create,
     * edit, or delete a template. Only valid templates are available to fill, but all templates can be 
     * edited or deleted.
     * 
     * @see CustomTemplateEngine::createModuleFolders() For initializing module folders.
     * @since 3.0
     */
    public function generateIndexPage()
    {
        $this->createModuleFolders();

        $rights = REDCap::getUserRights($this->userid);
        $participant_options = $this->getDropdownOptions($_GET["filter"]);
        $total = count($participant_options);

        $all_templates = array_diff(scandir($this->templates_dir), array("..", "."));
        $edit_templates = array();
        $valid_templates = array();

        // Grab all templates for current project
        foreach($all_templates as $template)
        {
            if (strpos($template, "_$this->pid.html") !== FALSE)
            {
                array_push($valid_templates, $template);
                array_push($edit_templates, $template);
            }
            else if (strpos($template, "_{$this->pid} - INVALID.html") !== FALSE)
            {
                array_push($edit_templates, $template);
            }
        }
        ?>
        <!-- boostrap-select files -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-select@1.13.15/dist/css/bootstrap-select.min.css">
        <script src="https://cdn.jsdelivr.net/npm/bootstrap-select@1.13.15/dist/js/bootstrap-select.min.js"></script>
        <!-- js-cookie-->
        <script src="https://cdn.jsdelivr.net/npm/js-cookie@2/src/js.cookie.min.js"></script>
        <!-- Module CSS -->
        <link rel="stylesheet" href="<?php print $this->getUrl("app.css"); ?>" type="text/css">
        <div class="container"> 
            <div class="jumbotron">
                <?php
                    $created = $_GET["created"];
                    if ($created === "1")
                    {
                        print "<div class='green container'>Your template was successfully saved</div><br/>";
                    }

                    $deleted = $_GET["deleted"];
                    if ($deleted === "1")
                    {
                        print "<div class='green container'>Your template was successfully deleted</div><br/>";
                    }
                    else if ($deleted === "0")
                    {
                        print "<div class='red container'>Something went wrong! Your template was not deleted. Please contact your REDCap administrator.</div><br/>";
                    }
                ?>
                <h3>Custom Template Engine</h3>
                <hr>
                <h4>This plugin allows you to create report templates and fill them with data from records in your REDCap project.</h4> 
                <br>
                <?php if ($rights[$this->userid]["reports"]) :?> 
                    <div class="container syntax-rule">
                        <a class="btn btn-link" href=<?php print $this->getUrl("CreateTemplate.php");?>>Create New Template</a> |
                        <?php if (sizeof($edit_templates) > 0):?>
                            <button type="button" class="btn btn-link" data-toggle="modal" data-target="#exampleModalCenter">Edit Template</button> | 
                        <?php else:?>
                            <button type="button" class="btn btn-link" data-toggle="modal" data-target="#exampleModalCenter" disabled>Edit Template</button> | 
                        <?php endif;?>
                        <?php if (sizeof($edit_templates) > 0):?>
                            <button type="button" class="btn btn-link" data-toggle="modal" data-target="#deleteTemplateModal">Delete Template</button>
                        <?php else:?>
                            <button type="button" class="btn btn-link" data-toggle="modal" data-target="#deleteTemplateModal" disabled>Delete Template</button>
                        <?php endif;?>
                    </div>
                <?php else:?>
                    <div class="container syntax-rule">
                        <span><i>You don't have permission to edit or create templates. Please contact your project administrator if you'd like to.</i></span>
                    </div>
                <?php endif; ?>
                <br/>
                <div class="container syntax-rule">
                    <p><i>Select the record(s) and template you wish to fill. Only valid templates will be accessible. Invalid templates must be edited before they can run.</i></p>
                    <form id="fill-template-form" action="<?php print $this->getUrl("FillTemplate.php");?>" method="post">
                        <table class="table" style="width:100%;">
                            <tbody>
                                <tr>
                                    <td colspan="2">
                                        <b>Total records: <?php print $total; ?></b>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="width:25%;">
                                        Choose up to 20 records
                                    </td>
                                    <td class="data">
                                        <input id="applyFilter" type="checkbox" <?php print $_GET["filter"] == "1" ? checked : ""; ?>>
                                        <label for="applyFilter">Filter records previously processed</label>
                                        <?php if (sizeof($participant_options) > 0):?>
                                            <select id="participantIDs" name="participantID[]" class="form-control selectpicker" style="background-color:white" data-live-search="true" data-max-options="20" multiple required>
                                            <?php 
                                                foreach($participant_options as $id => $option)
                                                {
                                                    print "<option value='$id'>$option</option>";
                                                }
                                            ?>
                                            </select>
                                            <p><i style="color:red">If you select more than 1 record, you are unable to preview the report before it downloads, and are unable to save it to a record field.</i></p>
                                            <p><i style="color:red">Large templates may take several seconds, when batch filling.</i></p>
                                        <?php else:?>
                                            <p>No Existing Records</p>        
                                        <?php endif;?>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="width:25%;">Choose Template</td>
                                    <td class="data">
                                        <?php if (sizeof($valid_templates) > 0):?>
                                            <select name="template" class="form-control">
                                                <?php
                                                    foreach($valid_templates as $template)
                                                    {
                                                        print "<option value=\"" . $template . "\">" . $template . "</option>";
                                                    }
                                                ?>
                                            </select>
                                        <?php else:?>
                                            <span>No Existing Templates</span>        
                                        <?php endif;?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <input type="hidden" name="download_token_value" id="download_token_value_id"/>
                        <div class="row">
                            <?php if (sizeof($valid_templates) > 0 && sizeof($participant_options) > 0):?>
                                <div class="col-md-3"><button id="fill-template-btn" type="submit" class="btn btn-primary">Fill Template</button></div>
                            <?php else:?>
                                <div class="col-md-6">
                                    <button id="fill-template-btn" type="submit" class="btn btn-primary" disabled>Fill Template</button>
                                    <span><i style="color:red"> **At least one record and one template must exist</i></span>
                                </div>
                            <?php endif;?>
                            <div id="progressBar" class="col"></div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <!-- Edit Template Modal -->
        <div class="modal fade" id="exampleModalCenter" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5>Choose a Template: </h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <form action=<?php print $this->getUrl("EditTemplate.php");?> method="post">
                        <div class="modal-body">
                            <select name="template" style="display:block; margin: 0 auto;" class="form-control">
                                <?php
                                    foreach($edit_templates as $template)
                                    {
                                        print "<option value=\"" . $template . "\">" . $template . "</option>";
                                    }
                                ?>        
                            </select>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Edit Template</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <!-- Delete Template Modals -->
        <div class="modal fade" id="deleteTemplateModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5>Choose a Template: </h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <select id="deleteTemplateDropdown" name="template" style="display:block; margin: 0 auto;" class="form-control">
                            <?php
                                foreach($edit_templates as $template)
                                {
                                    print "<option value=\"" . $template . "\">" . $template . "</option>";
                                }
                            ?>        
                        </select>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#checkDeletionModal">Delete Template</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal fade" id="checkDeletionModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5>Delete Template: </h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <form action=<?php print $this->getUrl("DeleteTemplate.php");?> method="post">
                        <div class="modal-body">
                            <div class="red"><p>Are you sure you want to delete <strong id="toDelete"></strong>?</p></div>
                            <input type="hidden" id="templateToDelete" name="templateToDelete">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Delete Template</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <script>
            $("#toDelete").text($("#deleteTemplateDropdown").val());
            $("#templateToDelete").val($("#deleteTemplateDropdown").val());
            $("#deleteTemplateDropdown").change(function() {
                $("#toDelete").text($(this).val());
                $("#templateToDelete").val($(this).val());
            });

            $("#participantIDs").change(function () {
                if ($(this).val().length > 1)
                {
                    $("#fill-template-btn").text("Download Reports");
                }
                else
                {
                    $("#fill-template-btn").text("Fill Template");
                }
            })

            var fileDownloadCheckTimer;
            $('#fill-template-form').submit(function () {
                if ($("#participantIDs").val().length > 1)
                {
                    $("#fill-template-btn").prop("disabled", true);
                    $("#progressBar").progressbar({value: false});

                    var token = new Date().getTime();
                    $('#download_token_value_id').val(token);
                    
                    fileDownloadCheckTimer = window.setInterval(function () {
                        var cookieValue = Cookies.get('fileDownloadToken');
                        if (cookieValue == token)
                        {
                            window.clearInterval(fileDownloadCheckTimer);
                            Cookies.remove('fileDownloadToken');
                            $("#fill-template-btn").prop("disabled", false);
                            $("#progressBar").progressbar("destroy");
                            $("#participantIDs").val("default");
                            $("#participantIDs").selectpicker("refresh");
                            $("#fill-template-btn").text("Fill Template");
                        }
                    }, 1000)
                }
            });

            $('#applyFilter').click(function () {
                var url = '<?php print $this->getUrl("index.php");?>';
                if($(this).prop('checked')) {
                    location.replace(url + '&filter=1');
                }
                else {
                    location.replace(url);
                }
            });
        </script>
        <?php
    }

    /**
     * Function called by external module that checks whether the user has permissions to use the module.
     * User needs permissions to export data in order to use module.
     * 
     * @param String $project_id    Project ID of current REDCap project.
     * @param String $link          Link that redirects to external module.
     * @return NULL Return null if the user doesn't have permissions to use the module. 
     * @return String Return link to module if the user has permissions to use it. 
     * @since 1.0
     */
    public function redcap_module_link_check_display($project_id, $link)
    {
        $rights = REDCap::getUserRights($this->userid);
        if ($rights[$this->userid]["data_export_tool"] === "0")
        {
            return NULL;
        }
        else
        {
            return $link;
        }
    }

    /**
     * getPaperSettings($name) 
     * Look for module project setting with name corresponding to the tempalte or file name provided
     * @param String $name Name of template or pdf document file name
     * @return Array Array with two elements: 1. paper size e.g. "Letter", "A4"; 2: paper orientation "Portrait" or "Landscape""
     */
    protected function getPaperSettings($fileOrTemplateName) 
    {
        $paperSize = "letter";
        $paperOrientation = "portrait";

        $templateSettings = $this->getSubSettings('template-options');

        foreach ($templateSettings as $settings) {
            // look for a template with name occurring within the file name of what's being generated 
            // (pretty horrid - will catch "MyTemplate" before "MyTemplate_New" - need better way of recording template names perhaps recording to project settings on create/save/delete and auto-generate template name for files system storage?)
            if (strpos($fileOrTemplateName, $settings['template-name']) !== false) {
                $paperSize = (empty($settings['option-paper-size'])) ? $paperSize : $settings['option-paper-size'];
                $paperOrientation = ($settings['option-paper-orientation']) ? "landscape" : $paperOrientation;
                break;
            }
        }

        return array($paperSize, $paperOrientation);
    }

    public function redcap_data_entry_form_top($project_id, $record, $instrument, $event_id, $group_id, $repeat_instance) 
    {
        global $Proj, $lang;

        // is there a file upload field on this form?
        $ff = false;
        foreach (array_keys($Proj->forms[$instrument]['fields']) as $f) {
            if ($Proj->metadata[$f]['element_type'] === 'file') {
                $ff = true;
                break;
            }
        }
        if (!$ff) return; // no file upload field -> return

        // any valid templates?
        $all_templates = array_diff(scandir($this->templates_dir), array("..", "."));
        $valid_templates = array();
        $suffix = "_$this->pid.html";
        foreach($all_templates as $template) {
            if (strpos($template, $suffix) !== FALSE) {
                array_push($valid_templates, rtrim($template, $suffix));
            }
        }

        if (count($valid_templates) === 0) return; // no templates -> return

        // make a select list of templates to include in the file upload dialog form for each field
        $templateSelect = '<select class="upload-source-select form-control form-control-sm"><option value="0">Choose file</option>';
        $n = 0;
        foreach($valid_templates as $template) {
            $n++;
            $templateSelect .= "<option value=\"$n\">$template</option>";
        }
        $templateSelect .= '</select>';
        $uploadDialogContent = '<div class="upload-source my-2 d-none"><div class="font-weight-bold"><i class="fas fa-cube mr-1"></i>Custom Template Engine</div><div>Choose a file or generate from a template '.$templateSelect.'</div><button class="upload-source-btn btn btn-primaryrc mt-2" style="font-size:14px;display:none;"><i class="fas fa-upload mr-1"></i>Fill template and upload</button></div>';
        echo $uploadDialogContent;

        $fillAndSaveUrl = $this->getUrl('FillAndSave.php');
        $fillAndSaveUrl .= "&id=".urlencode($record)."&event_id=$event_id&instrument=$instrument&instance=$repeat_instance";

        ?>
        <script type="text/javascript">
            $(document).ready(function(){
                $('body').on('dialogopen', function(event){
                    if(event.target.id=="file_upload") { //className=="fileuploadlink") {
                        var content = $('div.upload-source').clone();

                        $(content).find('button.upload-source-btn').on('click', function(e){
                            var templateName = $('#form_file_upload').find('select.upload-source-select').find(":selected").text();
                            e.preventDefault();
                            $('#form_file_upload').find('div.upload-source').hide();
                            $('#f1_upload_process').show();
                            $(this).prop('disabled, true');

                            $.ajax({
                                url: '<?=$fillAndSaveUrl?>',
                                type: 'POST',
                                dataType: 'json',
                                data: { 
                                    template_name: templateName,
                                    field_name: $('#field_name').val()
                                },
                                success: function(data) {
                                    console.log(data);
                                    window.parent.dataEntryFormValuesChanged = true;
                                    window.parent.window.stopUpload(data.result,data.field_name,data.doc_id,data.save_filename,data.record,data.doc_size,data.event_id,data.file_download_page,data.file_delete_page,data.doc_id_hash,data.instance);
                                    if (data.inlineActionTag) {
                                        window.parent.window.$(function(){ window.parent.window.initInlineImages(data.field_name) });
                                    }
                                },
                                error: function(data) {
                                    window.parent.window.stopUpload(0,'',0,'','','','','','','','');
                                    
                                }
                            });

                            return false;
                        });

                        $(content).find('select.upload-source-select').on('change', function() {
                            var selIdx = $(this).val();
                            var selName = $(this).find(":selected").text();
                            if (selIdx==0) {
                                // choose a file
                                $('#f1_upload_form').show();
                                $('#form_file_upload').find('button.upload-source-btn').hide();
                            } else { 
                                $('#f1_upload_form').hide();
                                $('#form_file_upload').find('button.upload-source-btn').show();
                            }
                        });
                        $(content).insertAfter('#this_upload_field').removeClass('d-none');
                    }
                });
            });
        </script>
        <?php
    }

    /**
     * fillAndSave
     * Takes $_POST from file upload field dialog, generates PDF of specified template for current record and uploads it to the field/event.
     */
    public function fillAndSave() {
        global $project_id, $Proj;
        $record = rawurldecode(urldecode($_REQUEST['id']));
        $event_id = $_REQUEST['event_id'];
        $instance = $_REQUEST['instance'];
        $field_name = explode('-', $_REQUEST['field_name'])[0];
        $template_name = $_REQUEST['template_name'];

        $save_filename = $template_name.'_'.$record.'_'.date('Y-m-d_His').'.pdf';
        $template_filename = $template_name.'_'.$project_id.'.html';
        $template = new Template($this->templates_dir, $this->compiled_dir);
        $filled_template = $template->fillTemplate($template_filename, $record);

        $doc = new DOMDocument();
        $doc->loadHTML($filled_template);
    
        $header = $doc->getElementsByTagName("header")->item(0);
        $footer = $doc->getElementsByTagName("footer")->item(0);
        $main = $doc->getElementsByTagName("main")->item(0);

        $filled_main = $doc->saveHTML($main);
        $filled_header = empty($header) ? "" : $doc->saveHTML($header);
        $filled_footer = empty($footer)? "" : $doc->saveHTML($footer);

        $dompdf = new Dompdf();
        $pdf_content = $this->createPDF($dompdf, $filled_header, $filled_footer, $filled_main, $template_name);
    
        $doc_size = strlen($pdf_content);
        $doc_id = $this->saveFileToField($save_filename, $pdf_content, $field_name, $record, $event_id, $instance, true);
        if ($doc_id) {
            $result = 1;
        } else {
            $msg = "Failed to generate PDF and save to field. <br>Template=$template_name; Record=$record; Field=$field_name ";
            \REDCap::logEvent("Custom Template Engine - Generate and Save Failed!", $msg);
            $result = 0;
        }

        
        // return response for upload as per DataEntry/file_upload.php
        // SURVEYS: Use the surveys/index.php page as a pass through for certain files (file uploads/downloads, etc.)
        if (isset($_GET['s']) && !empty($_GET['s']))
        {
            $file_download_page = APP_PATH_SURVEY . "index.php?pid=$project_id&__passthru=".urlencode("DataEntry/file_download.php");
            $file_delete_page   = APP_PATH_SURVEY . "index.php?pid=$project_id&__passthru=".urlencode("DataEntry/file_delete.php");
        }
        else
        {
            $file_download_page = APP_PATH_WEBROOT . "DataEntry/file_download.php?pid=$project_id";
            $file_delete_page   = APP_PATH_WEBROOT . "DataEntry/file_delete.php?pid=$project_id&page=" . $_GET['instrument'];
        }

        $return = array(
            'result' => $result,
            'field_name' => $field_name,
            'doc_id' => $doc_id,
            'save_filename' => $save_filename,
            'record' => js_escape($record),
            'doc_size' => " (" . round_up($doc_size/1024/1024) . " MB)",
            'event_id' => $event_id,
            'file_download_page' => $file_download_page,
            'file_delete_page' => $file_delete_page,
            'doc_id_hash' => \Files::docIdHash($doc_id),
            'instance' => $instance,
            'inlineActionTag' => (strpos($Proj->metadata[$field_name]['misc'], '@INLINE') !== false)
        );
        return $return;
    }
}
