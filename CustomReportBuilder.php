<?php

namespace BCCHR\CustomReportBuilder;

use REDCap;
use Project;
use DOMDocument;
use HtmlPage;

class CustomReportBuilder extends \ExternalModules\AbstractExternalModule 
{
    private $templates_dir;
    private $pid;
    private $userid;

    function __construct()
    {
        parent::__construct();
        $this->templates_dir = $this->getSystemSetting("templates-folder");
        $this->pid = $this->getProjectId();
        $this->userid = strtolower(USERID);
    }

    private function checkPermissions()
    {
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

    public function saveTemplate()
    {
        $header = preg_replace("/&nbsp;/", " ", $_POST["header-editor"]);
        $footer = preg_replace("/&nbsp;/", " ", $_POST["footer-editor"]);
        $data = preg_replace("/&nbsp;/", " ", $_POST["editor"]);

        $name = trim($_POST["templateName"]);
        $action = $_POST["action"];

        // Check if template has content and a name
        if (empty($data))
        {
            $HtmlPage = new HtmlPage();
            $HtmlPage->PrintHeaderExt();
            exit("<div class='warning'>Nothing was in the editor, therefore, no file was saved</div><a href='" . $this->getUrl("index.php") . "'>Back to Front</a>");
            $HtmlPage->PrintFooterExt();
        }
        else
        {
            // Save template
            if (!file_exists($this->templates_dir))
            {
                if (!mkdir($this->templates_dir))
                {
                    $HtmlPage = new HtmlPage();
                    $HtmlPage->PrintHeaderExt();
                    exit("<b>ERROR</b> Unable to create directory $this->templates_dir to store template. Please contact your REDCap administrator");
                    $HtmlPage->PrintFooterExt();
                }
            }
            else
            {
                // Validate Template
                $template = new Template($this->templates_dir);

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
                    }        
                }
                else
                {
                    $path_info = pathinfo($name);
                    
                    if (file_exists($this->templates_dir . $name))
                    {
                        if ($doc->saveHTMLFile($this->templates_dir . $name) === FALSE)
                        {
                            $other_errors[] = "<b>ERROR</b> Unable to save template. Please contact your REDCap administrator";
                        }
                        else
                        {
                            REDCap::logEvent("Template edited", $name);
                            if (!empty($template_errors) || !empty($header_errors) || !empty($footer_errors))
                            {
                                $filename = strpos($path_info["filename"], " - INVALID") !== FALSE ? $name : $path_info["filename"] . " - INVALID.html";
                                if ($name !== $filename)
                                {
                                    rename($this->templates_dir. $name, $this->templates_dir . $filename);
                                }
                            }
                            else
                            {
                                if (strpos($name, " - INVALID") !== FALSE)
                                {
                                    rename($this->templates_dir. $name, $this->templates_dir. str_replace(" - INVALID", "", $name));
                                }
                            }
                        }
                    }
                    else
                    {
                        $other_errors[] = "<b>ERROR</b> Template doesn't exist! Please contact your REDCap administrator about this";
                    }
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

    }

    public function generateFillTemplatePage()
    {
        $this->checkPermissions();
        
        $record = $_POST["participantID"];
        
        if (empty($record))
        {
            // OPTIONAL: Display the project header
            exit("<div class='red'>No record has been select. Please go back and select a record to fill the template.</div><a href='" . $this->getUrl("index.php") . "'>Back to Front</a>");
        }
        
        $template_filename = $_POST['template'];
        $template = new Template($this->templates_dir);
        
        $errors = array();
        try
        {
            $filled_template = $template->fillTemplate($template_filename, $record);
        
            $doc = new DOMDocument();
        
            $doc->loadHTML($filled_template);
        
            $header = $doc->getElementsByTagName("header")->item(0);
            $footer = $doc->getElementsByTagName("footer")->item(0);
        
            $body = $doc->getElementsByTagName("main")->item(0);
        
            $filled_body = $doc->saveHTML($body);
            $filled_header = empty($header) ? "" : $doc->saveHTML($header);
            $filled_footer = empty($footer)? "" : $doc->saveHTML($footer);
        }
        catch (Exception $e)
        {
            $errors[] = "<b>ERROR</b> [" . $e->getCode() . "] LINE [" . $e->getLine() . "] FILE [" . $e->getFile() . "] " . str_replace("Undefined index", "Field name does not exist", $e->getMessage());
        }
        ?>
        <link rel="stylesheet" href="app.css" type="text/css">
        <div class="container"> 
            <div class="jumbotron">
                <div class="row">
                    <div class="col-md-10">
                        <h3>Download Template</h3>
                    </div>
                    <div class="col-md-2">
                        <a class="btn btn-primary" style="color:white" href="index.php?pid=<?php print $this->pid;?>">Back to Front</a>
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
                </div>
                <br/>
                <form action="downloadFilledTemplate.php?pid=<?php print $this->pid; ?>&record=<?php print $record;?>" method="post">
                    <table class="table" style="width:100%;">
                        <tbody>
                            <tr>
                                <td style="width:25%;"><strong style="color:red">* Required</strong></td>
                                <td class="data">
                                    <div class="col-sm-5">
                                        <input id="filename" name="filename" type="text" class="form-control" value="<?php print basename($template_filename, "_$project_id.html") . " - $record";?>" required>
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
                            <?php print $filled_body; ?>
                        </textarea>
                    </div>
                </form>
            </div>
        </div>
        <?php 
        print "
        <script src='" . $this->getUrl("vendor/ckeditor/ckeditor/ckeditor.js") . "'></script>
        <script src='" . $this->getUrl("scripts.js") . "'></script>
        <script>
            CKEDITOR.dtd.\$removeEmpty['p'] = true;
            initializeEditor('header-editor', 200, '$plugins_folder', '$this->pid')
            initializeEditor('footer-editor', 200,  '$plugins_folder', '$this->pid')
            initializeEditor('editor', 1000, '$plugins_folder', '$this->pid');
        </script>
        ";        
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
        <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.6.1/css/all.css" integrity="sha384-gfdkjb5BdAXd+lj+gudLWI+BXq4IuLW5IT+brZEZsLFm++aCMlF1V92rMkPaX4PP" crossorigin="anonymous">
        <div class="container"> 
            <div class="jumbotron">
                <div class="row">
                    <div class="col-md-10">
                        <h3>Edit Template</h3>
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
                                        <input name="templateName" type="text" class="form-control" value="<?php print $template_name; ?>" disabled>
                                        <input type="hidden" name="action" value="edit">
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
        <?php
        print "
        <script src='" . $this->getUrl("vendor/ckeditor/ckeditor/ckeditor.js") . "'></script>
        <script src='" . $this->getUrl("scripts.js") . "'></script>
        <script>
            initializeEditor('header-editor', 200, '$plugins_folder', '$this->pid')
            initializeEditor('footer-editor', 200,  '$plugins_folder', '$this->pid')
            initializeEditor('editor', 1000, '$plugins_folder', '$this->pid')
        </script>
        ";
    }

    public function generateCreateTemplatePage()
    {
        $this->checkPermissions();
        ?>
        <link rel="stylesheet" href="<?php print $this->getUrl("app.css"); ?>" type="text/css">
        <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.6.1/css/all.css" integrity="sha384-gfdkjb5BdAXd+lj+gudLWI+BXq4IuLW5IT+brZEZsLFm++aCMlF1V92rMkPaX4PP" crossorigin="anonymous">
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
        <?php
        print "
        <script src='" . $this->getUrl("vendor/ckeditor/ckeditor/ckeditor.js") . "'></script>
        <script src='" . $this->getUrl("scripts.js") . "'></script>
        <script>
            initializeEditor('header-editor', 200, '$plugins_folder', '$this->pid')
            initializeEditor('footer-editor', 200,  '$plugins_folder', '$this->pid')
            initializeEditor('editor', 1000, '$plugins_folder', '$this->pid')
        </script>
        ";
    }

    public function generateIndexPage()
    {
        $rights = REDCap::getUserRights($this->userid);
        if ($rights["data_export_tool"] === "0")
        {
            // OPTIONAL: Display the project header
            exit("<div class='red'>You don't have premission to access this module. Please contact your project administrator.</div>");
        }

        $query = "select secondary_pk, secondary_pk_display_value, secondary_pk_display_label, custom_record_label from redcap_projects where project_id = $this->pid";
        $result = $this->query($query);
        while ($row = db_fetch_assoc($result)) {
            // Do something with this row from redcap_data
            $secondary_pk = $row["secondary_pk"];
            $display_value = $row["secondary_pk_display_value"];
            $display_label = $row["secondary_pk_display_label"];
            $custom_record_label = $row["custom_record_label"];
        }

        $id_field = REDCap::getRecordIdField();

        if (!empty($secondary_pk) && $display_value == "1")
        {
            if ($display_label == "1")
            {
                $dictionary = REDCap::getDataDictionary("array", FALSE, $secondary_pk);
                $secondary_pk_label = $dictionary[$secondary_pk]["field_label"];
            }
        }

        if (!empty($custom_record_label))
        {
            if (REDCap::isLongitudinal())
            {
                preg_match_all("/\[[0-9a-zA-Z_-]*\]\[[0-9a-zA-Z_-]*\]/", $custom_record_label, $fields);

                foreach($fields[0] as $index => $field)
                {
                    $start = strpos($field, "[") + 1;
                    $mid = strpos($field, "][") + 2;
                    $end = strpos($field, "]", $mid);

                    $fields_to_replace[] = array(
                        "event" => substr($field, $start, $mid - $start - 2),
                        "field" => substr($field, $mid, $end - $mid)
                    );

                    $labels_to_replace[] = $field;
                }
            }
            {
                preg_match_all("/\[[0-9a-zA-Z_]*\]/", $custom_record_label, $fields);
                foreach($fields[0] as $index => $field)
                {
                    $fields_to_replace[] = substr($field, 1, strlen($field) - 2);
                    $labels_to_replace[] = $field;
                }
            }
        }

        $participant_options = array();

        $participant_ids = REDCap::getData("json", null, null, null, $rights[$user]["group_id"]);

        // Grab the record id, the first field, of each record in the project and the secondary id field
        if (REDCap::isLongitudinal())
        {   
            $options = array();

            foreach(json_decode($participant_ids) as $json)
            {
                $id = $json->$id_field;

                if (empty($options[$id]))
                {
                    $options[$id] = array();
                }

                if ($display_value == "1" && !empty($json->$secondary_pk) && empty($options[$id]["secondary_pk"]))
                {
                    $options[$id]["secondary_pk"] = $json->$secondary_pk;
                }

                foreach($fields_to_replace as $field)
                {   
                    if ($json->redcap_event_name == $field["event"])
                    {
                        $f = $field["field"];
                        $options[$id]["label_replacements"][] = $json->$f;
                    }
                }
            }

            foreach($options as $id => $option)
            {
                $custom_label = empty($custom_record_label) || empty($option["label_replacements"]) ? "" : str_replace($labels_to_replace, $option["label_replacements"], $custom_record_label);

                $id2 = ($display_value == "1" && !empty($option["secondary_pk"])) ? "( $secondary_pk_label " . $option["secondary_pk"] . " )" : "";

                $label  = "$id $id2 $custom_label";
                    
                $participant_options[$id] = $label;
            }
        }
        else
        {
            foreach(json_decode($participant_ids) as $json)
            {
                $id = $json->$id_field;
                    
                $id2 = ($display_value == "1" && !empty($json->$secondary_pk)) ? "( $secondary_pk_label " . $json->$secondary_pk . " )" : "";

                $replacements = array();
                foreach($fields_to_replace as $index => $field)
                {
                    $replacements[] = empty($json->$field) ? "" : $json->$field;
                }
                $custom_label = empty($custom_record_label) ? "" : str_replace($labels_to_replace, $replacements, $custom_record_label);

                $participant_options[$id] = "$id $id2 $custom_label";
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
                ?>
                <h3>REDCap Report Builder</h3>
                <hr>
                <h4>This plugin allows you to create report templates and fill them with data from records in your REDCap project.</h4> 
                <br>
                <?php if ($rights[$this->userid]["reports"]) :?> 
                    <div class="container syntax-rule">
                        <a class="btn btn-link" href=<?php print $this->getUrl("CreateTemplate.php");?>>Create New Template</a> |
                        <?php if (sizeof($edit_templates) > 0):?>
                            <button type="button" class="btn btn-link" data-toggle="modal" data-target="#exampleModalCenter">Edit Existing Template</button>
                        <?php else:?>
                            <button type="button" class="btn btn-link" data-toggle="modal" data-target="#exampleModalCenter" disabled>Edit Existing Template</button>
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
        <?php 
            print "<script> var options = [";
            foreach($participant_options as $id => $option)
            {
                print "{label: \"$option\", value: \"$id\"},";
            }
            print "]; </script>";
        ?>
        <script>
            $(function() {
                $("#participantIDs" ).autocomplete({
                    minLength: 0,
                    source: options
                    }).focus(function () {
                        $(this).autocomplete("search", "");
                    });
            });
        </script>
        <?php
    }
}