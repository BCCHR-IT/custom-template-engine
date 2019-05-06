<?php

namespace BCCHR\CustomReportBuilder;

require_once "Template.php";
require_once "vendor/autoload.php";

use REDCap;
use Project;
use Records;
use DOMDocument;
use HtmlPage;
use Dompdf\Dompdf;

class CustomReportBuilder extends \ExternalModules\AbstractExternalModule 
{
    private $templates_dir;
    private $compiled_dir;
    private $img_dir;
    private $pid;
    private $userid;

    function __construct()
    {
        parent::__construct();
        $this->templates_dir = $this->getSystemSetting("templates-folder");
        $this->compiled_dir = $this->getSystemSetting("compiled-templates-folder");
        $this->img_dir = $this->getSystemSetting("img-folder");
        $this->pid = $this->getProjectId();
        $this->userid = strtolower(USERID);
    }

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

    private function initializeEditor($id, $height)
    {
        ?>
        <script>
            CKEDITOR.plugins.addExternal('codemirror', '<?php print $this->getUrl("vendor/ckeditor-plugin/codemirror/codemirror/plugin.js"); ?>');
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
                font_names: 'Arial/Arial, Helvetica, sans-serif; Times New Roman/Times New Roman, Times, serif; Courier; DejaVu'
            });
        </script>
        <?php
    }

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
            exit("<div class='red'>You don't have premission to view this page</div><a href='" . $this->getUrl("index.php") . "'>Back to Front</a>");
        }
    }

    private function generateInstructions()
    {
        ?>
        <div class="container syntax-rule">
            <h4><u>Instructions</u></h4>
            <p>Build your template in the WYSIWYG editor using the syntax guidelines below. Variables that you wish to pull must be contained in this template. You may format the template however you wish, including using tables.<strong style="color:red"> This plugin currently doesn't work with repeatable events.</strong></p>
            <p>**The project id will be appended to the template name for identification purposes**</p>
            <p><strong style="color:red">**IMPORTANT**</strong> Any image uploaded to the plugin will be saved for future use by <strong>ALL</strong> users. <strong>Do not upload any identifying images.</strong></p>
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
                    <button class="collapsible">Query checkbox values using <strong>in_array</strong> <span class="fas fa-caret-down"></span><span class="fas fa-caret-up"></span></button>
                    <div class="collapsible-content">
                        <p><u>Syntax:</u> <strong>{if in_array('someValue', $redcap['variable'])}</strong> show this text <strong>{/if}</strong></p>
                        <div class="syntax-example">
                            Example:
                            <div>
                                <strong>{if in_array('Monday', $redcap['weekdays'])}</strong> The day of the week is {$redcap['weekdays']} <strong>{/if}</strong>
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
                    <button class="collapsible">Print all checkbox values using <strong>{$redcap['variable']['allValues']}</strong> <span class="fas fa-caret-down"></span><span class="fas fa-caret-up"></span></button>
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
                <?php if (REDCap::isLongitudinal()): ?>
                    <u>NOTE:</u> Fields are sorted by their instruments, and are preformatted for ease of use. For Longitudinal projects, this sytnax will default to the first event in a record's arm.
                    To access other events please append their name before the field (<i>See adding events for longitdinal projects, under syntax rules</i>).
                <?php else:?>
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
                                $valuesAndLabels = explode(" | ", $field["select_choices_or_calculations"]);
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
                                        print "\"" . trim($label) . "\", ";
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
            </div>
        </div>
        <?php
    }

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
                            $url = $this->img_dir . $filename;
                            REDCap::logEvent("Photo uploaded", $this->img_dir . $filename);
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
                        array_push(
                            $all_imgs,
                            array(
                                "url" => $this->img_dir . $img,
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
                        <div class='col-sm-2'>
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

    public function deleteTemplate()
    {
        $templateToDelete = $_POST["templateToDelete"];
        if (unlink($this->templates_dir . $templateToDelete))
        {
            REDCap::logEvent("Deleted template", $templateToDelete);
            return TRUE;
        }
        else
        {
            return FALSE;
        }
    }

    public function saveTemplate()
    {
        $header = REDCap::filterHtml(preg_replace("/&nbsp;/", " ", $_POST["header-editor"]));
        $footer = REDCap::filterHtml(preg_replace("/&nbsp;/", " ", $_POST["footer-editor"]));
        $data = REDCap::filterHtml(preg_replace("/&nbsp;/", " ", $_POST["editor"]));

        $name = trim($_POST["templateName"]);
        $action = $_POST["action"];

        // Check if template has content and a name
        if (empty($data))
        {
            $HtmlPage = new HtmlPage();
            $HtmlPage->PrintHeaderExt();
            exit("<div class='yellow'>Nothing was in the editor, therefore, no file was saved</div><a href='" . $this->getUrl("index.php") . "'>Back to Front</a>");
            $HtmlPage->PrintFooterExt();
        }
        else
        {
            // Save template
            
            // Validate Template
            $template = new Template($this->templates_dir, $this->compiled_dir);

            $template_errors = $template->validateTemplate($data);
            $header_errors = $template->validateTemplate($header);
            $footer_errors = $template->validateTemplate($footer);

            $doc = new DOMDocument();
            $doc->loadHTML("<html><body><header>$header</header><footer>$footer</footer><main>$data</main></body></html>");

            if ($action === "create")
            {  
                if (!file_exists("$this->templates_dir{$name}_$this->pid.html") && !file_exists("$this->templates_dir{$name}_{$this->pid} - INVALID.html"))
                {
                    $filename = !empty($template_errors) || !empty($header_errors) || !empty($footer_errors) ? "{$name}_{$this->pid} - INVALID.html" : "{$name}_$this->pid.html";
                    if ($doc->saveHTMLFile($this->templates_dir . $filename) === FALSE)
                    {
                        $other_errors[] = "<b>ERROR</b> Unable to save template. Please contact your REDCap administrator";
                    }
                    else
                    {
                        REDCap::logEvent("Template created", $filename);
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
                            REDCap::logEvent("Template edited", $currTemplateName);
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
                            if (!empty($template_errors) || !empty($header_errors) || !empty($footer_errors))
                            {
                                $filename = "{$name}_$this->pid - INVALID.html";
                                rename($this->templates_dir. $currTemplateName, $this->templates_dir . $filename);
                            }
                            else
                            {
                                $filename = "{$name}_$this->pid.html";
                                rename($this->templates_dir. $currTemplateName, $this->templates_dir . $filename);
                            }
                            REDCap::logEvent("Template edited", "Renamed template from '$currTemplateName' to '$filename'");
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
        
        if (!empty($errors))
        {
            return array(
                "errors" => $errors,
                "main" => $data,
                "header" => $header,
                "footer" => $footer,
                "templateName" => $filename
            );
        }
        else
        {
            return TRUE;
        }
    }

    public function downloadTemplate()
    {
        $header = REDCap::filterHtml(preg_replace("/&nbsp;/", " ", $_POST["header-editor"]));
        $footer = REDCap::filterHtml(preg_replace("/&nbsp;/", " ", $_POST["footer-editor"]));
        $main = REDCap::filterHtml(preg_replace("/&nbsp;/", " ", $_POST["editor"]));
        $filename = REDCap::escapeHtml($_POST["filename"]);

        if (isset($main) && !empty($main))
        {
            $doc = new DOMDocument();
            $doc->loadHtml("
                <html>
                    <body>
                        <header>$header</header>
                        <footer>$footer</footer>
                        <main>$main</main>
                        <script type='text/php'>
                            // Add page number to every page
                            if (isset(\$pdf)) { 
                                \$pdf->page_script('
                                    \$font = \$fontMetrics->get_font(\"Arial, Helvetica, sans-serif\", \"normal\");
                                    \$size = 12;
                                    \$pageText = \"Page \" . \$PAGE_NUM . \" of \" . \$PAGE_COUNT;
                                    \$y = 750;
                                    \$x = 270;
                                    \$pdf->text(\$x, \$y, \$pageText, \$font, \$size);
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
                $style = $doc->createElement("style", "body, body > table { font-size: 12px; margin-top: 25px; } header { position: fixed; left: 0px; top: -100px; } footer { position: fixed; left: 0px; bottom:0px; } @page { margin: 130px 50px; }");
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

            // Add page numbers to the footer of every page

            //print htmlspecialchars($doc->saveHtml());

            $dompdf = new Dompdf();
            $dompdf->set_option("isHtml5ParserEnabled", true);
            $dompdf->set_option("isPhpEnabled", true);
            $dompdf->loadHtml($doc->saveHtml());

            // Setup the paper size and orientation
            $dompdf->setPaper("letter", "portrait");

            // Render the HTML as PDF
            $dompdf->render();

            if ($this->getProjectSetting("save-report-to-repo"))
            {
                // Stolen code from redcap version/FileRepository/index.php with several modifications
                // Upload the compiled report to the File Repository
                $database_success = FALSE;
                $upload_success = FALSE;
                $errors = array();

                $dummy_file_name = $filename;
                $dummy_file_name = preg_replace("/[^a-zA-Z-._0-9]/","_",$dummy_file_name);
                $dummy_file_name = str_replace("__","_",$dummy_file_name);
                $dummy_file_name = str_replace("__","_",$dummy_file_name);
                
                $file_extension = "pdf";
                $stored_name = date('YmdHis') . "_pid" . $this->pid . "_" . generateRandomHash(6) . ".pdf";

                $upload_success = file_put_contents(EDOC_PATH . $stored_name, $dompdf->output());

                if ($upload_success !== FALSE) 
                {
                    $dummy_file_size = $upload_success;
                    $dummy_file_type = "application/pdf";
                    
                    $file_repo_name = date("Y/m/d H:i:s");

                    $sql = "INSERT INTO redcap_docs (project_id,docs_date,docs_name,docs_size,docs_type,docs_comment,docs_rights)
                            VALUES ($this->pid,CURRENT_DATE,'$dummy_file_name.pdf','$dummy_file_size','$dummy_file_type',
                            \"$file_repo_name - $filename ($this->userid)\",NULL)";
                                    
                    if ($this->query($sql)) 
                    {
                        $docs_id = db_insert_id();

                        $sql = "INSERT INTO redcap_edocs_metadata (stored_name,mime_type,doc_name,doc_size,file_extension,project_id,stored_date)
                                VALUES('".$stored_name."','".$dummy_file_type."','".$dummy_file_name."','".$dummy_file_size."',
                                '".$file_extension."','".$this->pid."','".date('Y-m-d H:i:s')."');";
                                    
                        if ($this->query($sql)) 
                        {
                            $doc_id = db_insert_id();
                            $sql = "INSERT INTO redcap_docs_to_edocs (docs_id,doc_id) VALUES ('".$docs_id."','".$doc_id."');";
                                        
                            if ($this->query($sql)) 
                            {
                                if ($project_language == 'English') 
                                {
                                    // ENGLISH
                                    $context_msg_insert = "{$lang['docs_22']} {$lang['docs_08']}";
                                } 
                                else 
                                {
                                    // NON-ENGLISH
                                    $context_msg_insert = ucfirst($lang['docs_22'])." {$lang['docs_08']}";
                                }

                                // Logging
                                REDCap::logEvent("Custom Report Builder - Uploaded document to file repository", "Successfully uploaded $filename");
                                $context_msg = str_replace('{fetched}', '', $context_msg_insert);
                                $database_success = TRUE;
                            } 
                            else 
                            {
                                /* if this failed, we need to roll back redcap_edocs_metadata and redcap_docs */
                                $this->query("DELETE FROM redcap_edocs_metadata WHERE doc_id='".$doc_id."';");
                                $this->query("DELETE FROM redcap_docs WHERE docs_id='".$docs_id."';");
                                $this->deleteRepositoryFile($stored_name);
                            }
                        } 
                        else
                        {
                            /* if we failed here, we need to roll back redcap_docs */
                            $this->query("DELETE FROM redcap_docs WHERE docs_id='".$docs_id."';");
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
                                    
                    if ($super_user) 
                    {
                        $context_msg .= '<br><br>' . $lang['system_config_69'];
                    }

                    $HtmlPage = new HtmlPage();
                    $HtmlPage->PrintHeaderExt();
                    print "<div class='red'>" . $context_msg . "</div><a href='" . $this->getUrl("index.php") . "'>Back to Front</a>";
                    $HtmlPage->PrintFooterExt();
                }
                else
                {
                    // Output the generated PDF to Browser
                    $dompdf->stream($filename);
                    REDCap::logEvent("Downloaded Report ", $filename , "" ,$_GET["record"]);
                }
            }
            else
            {
                $dompdf->stream($filename);
                REDCap::logEvent("Downloaded Report ", $filename , "" ,$_GET["record"]);
            }
        }
        else
        {
            $HtmlPage = new HtmlPage();
            $HtmlPage->PrintHeaderExt();
            print "<div class='yellow'>Nothing was in the editor, therefore, no file was downloaded</div><a href='" . $this->getUrl("index.php") . "'>Back to Front</a>";
            $HtmlPage->PrintFooterExt();
        }
    }

    public function generateFillTemplatePage()
    {
        $rights = REDCap::getUserRights($this->userid);
        if ($rights[$this->userid]["data_export_tool"] === "0") 
        {
            exit("<div class='red'>You don't have premission to view this page</div><a href='" . $this->getUrl("index.php") . "'>Back to Front</a>");
        }
        
        $record = $_POST["participantID"];
        if (empty($record))
        {
            // OPTIONAL: Display the project header
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
                    <p>You may download the report as is, or edit until you're satisfied, then download. You may also copy/paste the report into another editor and save, if you prefer a format other than PDF</p>
                    <p><strong style="color:red">**IMPORTANT**</strong></p>
                    <ul>
                        <li>Tables and images may be cut off in PDF, because of size. If so, there is no current fix and you must edit your content until it fits. Some suggestions are to break up content into
                        multiple tables, shrink font, etc...</li>
                        <li>Any image uploaded to the plugin will be saved for future use by <strong>ALL</strong> users. <strong>Do not upload any identifying images.</strong></li>
                        <?php if ($rights[$user]["data_export_tool"] === "2") :?>
                            <li> Data has been de-identified according to user access rights</li>
                        <?php endif;?>
                    </ul>
                    <?php if ($this->getProjectSetting("save-report-to-repo")) :?>
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
                                    <div class="col-sm-5">
                                        <input id="filename" name="filename" type="text" class="form-control" value="<?php print basename($template_filename, "_$this->pid.html") . " - $record";?>" required>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td><button id="download-pdf" type="submit" class="btn btn-primary">Download PDF</button></td>
                            </tr>
                        </tbody>
                    </table>
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
        <script>CKEDITOR.dtd.\$removeEmpty['p'] = true;</script>
        <?php
        $this->initializeEditor("header-editor", 200);
        $this->initializeEditor("footer-editor", 200);
        $this->initializeEditor("editor", 1000);
    }

    public function generateEditTemplatePage($info = NULL)
    {
        $this->checkPermissions();
        if (!empty($info))
        {
            $errors = $info["errors"];
            $header_data = $info["header"];
            $footer_data = $info["footer"];
            $main_data = $info["main"];
            $template_name = $info["templateName"];
        }
        else
        {
            $template_name = $_POST["template"];
            $template = file_get_contents($this->templates_dir . $template_name);

            $doc = new DOMDocument();
            $doc->loadHTML($template);

            $header = $doc->getElementsByTagName("header")->item(0);
            $footer = $doc->getElementsByTagName("footer")->item(0);
            $main = $doc->getElementsByTagName("main")->item(0);

            $main_data = $doc->saveHTML($main);
            $header_data = empty($header) ? "" : $doc->saveHTML($header);
            $footer_data = empty($footer)? "" : $doc->saveHTML($footer);
        }
        ?>
        <link rel="stylesheet" href="<?php print $this->getUrl("app.css"); ?>" type="text/css">
        <div class="container"> 
            <div class="jumbotron">
                <div class="row">
                    <div class="col-md-10">
                        <?php if (!empty($errors) && (file_exists("$this->templates_dir{$template_name}_$this->pid.html") || file_exists("$this->templates_dir{$template_name}_$this->pid - INVALID.html"))):?>
                            <h3>Create Template</h3>
                        <?php else: ?>
                            <h3>Edit Template</h3>
                        <?php endif;?>
                    </div>
                    <div class="col-md-2">
                        <a class="btn btn-primary" style="color:white" href="<?php print $this->getUrl("index.php")?>">Back to Front</a>
                    </div>
                </div>
                <hr/>
                <?php if (!empty($info)) :?>
                    <div class="red container">
                        <h4>Template Validation Failed!</h4>
                        <p>Template was saved with the following errors...</p>
                        <p><a id="readmore-link" href="#">Click to view errors</a></p>
                        <div id="readmore" style="display:none">
                            <?php if (sizeof($errors['otherErrors']) > 0): ?>
                                <p><strong>General Errors...</strong></p>
                                <div>
                                <?php
                                    foreach($errors['otherErrors'] as $error)
                                    {
                                        print "<p>$error</p>";
                                    }
                                ?>
                                </div>
                            <?php endif; ?>
                            <?php if (sizeof($errors['headerErrors']) > 0): ?>
                                <p><strong>Header Errors...</strong></p>
                                <div>
                                <?php
                                    foreach($errors['headerErrors'] as $error)
                                    {
                                        print "<p>$error</p>";
                                    }
                                ?>
                                </div>
                            <?php endif; ?>
                            <?php if (sizeof($errors['footerErrors']) > 0): ?>
                                <p><strong>Footer Errors...</strong></p>
                                <div>
                                <?php
                                    foreach($errors['footerErrors'] as $error)
                                    {
                                        print "<p>$error</p>";
                                    }
                                ?>
                                </div>
                            <?php endif; ?>
                            <?php if (sizeof($errors['templateErrors']) > 0): ?>
                                <p><strong>Body Errors...</strong></p>
                                <div>
                                <?php
                                    foreach($errors['templateErrors'] as $error)
                                    {
                                        print "<p>$error</p>";
                                    }
                                ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <hr/>
                <?php endif;?>
                <?php $this->generateInstructions() ?>
                <br/><br/>
                <form action="<?php print $this->getUrl("SaveTemplate.php"); ?>" method="post">
                    <table class="table" style="width:100%;">
                        <tbody>
                            <tr>
                                <td style="width:25%;">Template Name <strong style="color:red">*Required</strong></td>
                                <td class="data">
                                    <div class="col-sm-5">
                                        <input name="templateName" type="text" class="form-control" value="<?php print str_replace(array("_$this->pid", " - INVALID", ".html"), "", $template_name); ?>">
                                        <input type="hidden" name="action" 
                                            value="<?php !empty($errors) && (file_exists("$this->templates_dir{$template_name}_$this->pid.html") || file_exists("$this->templates_dir{$template_name}_$this->pid - INVALID.html")) ?
                                                     print "create" : print "edit"?>"
                                        >
                                        <input name="currTemplateName" type="hidden" class="form-control" value="<?php print $template_name; ?>">
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td style="width:25%;"><button type="submit" class="btn btn-primary">Save Template</button></td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="collapsible-container">
                        <button type="button" class="collapsible">Add Header **Optional** <span class="fas fa-caret-down"></span><span class="fas fa-caret-up"></span></button>
                        <div class="collapsible-content"> 
                            <p>Anything in the header will appear at the top of every page in the template. All syntax rules apply. <strong>If the header content is too big, it will overlap template data in the PDF.</strong></p>
                            <textarea cols="80" id="header-editor" name="header-editor" rows="10">
                                <?php print $header_data; ?>
                            </textarea>
                        </div>
                    </div>
                    <div class="collapsible-container">
                        <button type="button" class="collapsible">Add Footer **Optional** <span class="fas fa-caret-down"></span><span class="fas fa-caret-up"></span></button>
                        <div class="collapsible-content">
                            <p>Anything in the footer will appear at the bottom of every page in the template. All syntax rules apply. <strong>If the footer content is too big, it will cutoff in the PDF.</strong></p>
                            <textarea cols="80" id="footer-editor" name="footer-editor" rows="10">
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
        <?php
        $this->initializeEditor("header-editor", 200);
        $this->initializeEditor("footer-editor", 200);
        $this->initializeEditor("editor", 1000);
    }

    public function generateCreateTemplatePage()
    {
        $this->checkPermissions();
        ?>
        <link rel="stylesheet" href="<?php print $this->getUrl("app.css"); ?>" type="text/css">
        <div class="container"> 
            <div class="jumbotron">
                <div class="row">
                    <div class="col-md-10">
                            <h3>Create New Template</h3>
                    </div>
                    <div class="col-md-2">
                        <a class="btn btn-primary" style="color:white" href="<?php print $this->getUrl("index.php")?>">Back to Front</a>
                    </div>
                </div>
                <hr/>
                <?php $this->generateInstructions() ?>
                <br/><br/>
                <form action="<?php print $this->getUrl("SaveTemplate.php"); ?>" method="post">
                    <table class="table" style="width:100%;">
                        <tbody>
                            <tr>
                                <td style="width:25%;">Template Name <strong style="color:red">*Required</strong></td>
                                <td class="data">
                                    <div class="col-sm-5">
                                        <input name="templateName" type="text" class="form-control" required>
                                        <input type="hidden" name="action" value="create">
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td style="width:25%;"><button type="submit" class="btn btn-primary">Save Template</button></td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="collapsible-container">
                        <button type="button" class="collapsible">Add Header **Optional** <span class="fas fa-caret-down"></span><span class="fas fa-caret-up"></span></button>
                        <div class="collapsible-content"> 
                            <p>Anything in the header will appear at the top of every page in the template. All syntax rules apply. <strong>If the header content is too big, it will overlap template data in the PDF.</strong></p>
                            <textarea cols="80" id="header-editor" name="header-editor" rows="10"></textarea>
                        </div>
                    </div>
                    <div class="collapsible-container">
                        <button type="button" class="collapsible">Add Footer **Optional** <span class="fas fa-caret-down"></span><span class="fas fa-caret-up"></span></button>
                        <div class="collapsible-content">
                            <p>Anything in the footer will appear at the bottom of every page in the template. All syntax rules apply. <strong>If the footer content is too big, it will cutoff in the PDF.</strong></p>
                            <textarea cols="80" id="footer-editor" name="footer-editor" rows="10"></textarea>
                        </div>
                    </div>
                    <div style="margin-top:20px">
                        <textarea cols="80" id="editor" name="editor" rows="10">
                        </textarea>
                    </div>
                </form>
            </div>
        </div>
        <script src="<?php print $this->getUrl("vendor/ckeditor/ckeditor/ckeditor.js"); ?>"></script>
        <script src="<?php print $this->getUrl("scripts.js"); ?>"></script>
        <?php
        $this->initializeEditor("header-editor", 200);
        $this->initializeEditor("footer-editor", 200);
        $this->initializeEditor("editor", 1000);
    }

    public function generateIndexPage()
    {
        $this->createModuleFolders();

        $rights = REDCap::getUserRights($this->userid);

        $participant_options = array();

        $id_field = REDCap::getRecordIdField();
        $data = json_decode(REDCap::getData("json", null, array($id_field), null, $rights[$user]["group_id"]), true);

        foreach($data as $record)
        {
            $to_add = $record[$id_field];
            if (!in_array($to_add, array_keys($participant_options)))
            {
                $arm = REDCap::isLongitudinal() ? array_pop(explode("arm_", $record["redcap_event_name"])) : "1";
                $label = Records::getCustomRecordLabelsSecondaryFieldAllRecords($to_add, true, $arm, false, '');

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
                <h3>REDCap Report Builder</h3>
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
                    <p><i>Select the record and template you wish to fill. Only valid templates will be accessible. Invalid templates must be edited before they can run.</i></p>
                    <form action=<?php print $this->getUrl("FillTemplate.php"); ?> method="post">
                        <table class="table" style="width:100%;">
                            <tbody>
                                <tr>
                                    <td colspan="2">
                                        <b>Total records: <?php print $total; ?></b>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="width:25%;">
                                        Choose an existing Record ID
                                    </td>
                                    <td class="data">
                                        <?php if (sizeof($participant_options) > 0):?>
                                            <input  name="participantID" id="participantIDs" class="form-control" style="width:initial;" required>
                                        <?php else:?>
                                            <span>No Existing Records</span>        
                                        <?php endif;?>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="width:25%;">Choose Template</td>
                                    <td class="data">
                                        <?php if (sizeof($valid_templates) > 0):?>
                                            <select name="template" class="form-control" style="width:initial;">
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
                        <?php if (sizeof($valid_templates) > 0 && sizeof($participant_options) > 0):?>
                            <button type="submit" class="btn btn-primary">Fill Template</button>
                        <?php else:?>
                            <button type="submit" class="btn btn-primary" disabled>Fill Template</button>
                            <span><i style="color:red"> **At least one record and one template must exist</i></span>
                        <?php endif;?>
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
            var options = [
                <?php 
                    foreach($participant_options as $id => $option)
                    {
                        print "{label: '$option', value: '$id'},";
                    }
                ?>
            ]

            $(function() {
                $("#participantIDs" ).autocomplete({
                    minLength: 0,
                    source: options
                    }).focus(function () {
                        $(this).autocomplete("search", "");
                });
                $("#toDelete").text($("#deleteTemplateDropdown").val());
                $("#templateToDelete").val($("#deleteTemplateDropdown").val());
                $("#deleteTemplateDropdown").change(function() {
                    $("#toDelete").text($(this).val());
                    $("#templateToDelete").val($(this).val());
                });
            });
        </script>
        <?php
    }

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
}