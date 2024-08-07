# Custom Template Engine
REDCap module that allows you to create HTML templates and fill them with record data from your project. You can download the filled templates as a PDF.

This module works for classic, longitudinal, and multiple arm projects, and the last instances of repeatable events and instruments.

## IMPORTANT: Switching from Custom Report Builder to Custom Template Engine

As of 2019-08-23, the Custom Report Builder has been rebranded as the Custom Template Engine, and has been re-submitted as a new module in the REDcap External Module Repository. __All subsequent updates will be made to the Custom Template Engine, not to the Custom Report Builder__

When switching from the old module to the rebranded module, you must disable the old module first!

## Configuration

### System Configurations
- Saved Templates Folder: Required configuration that defines the storage location of all created templates. 
- Compiled Templates Folder: Required configuration that defines the storage of compiled templates generated by the PHP template engine Smarty.
- Images Folder: Required configuration that defines the publically accessible storage of images uploaded by users. 

## Project Configurations
- Save Filled Templates to File Repository: This is specific to downloading a filled template, and will save a copy of the PDF to the File Repository. 

## Permissions

Users must have data export access in order to use the module, and perhaps additional permissions, listed below, to use certain features. 

## Creating/Editing a Template

Users must have access to data exports and reports in order to edit or create a template. The content will be created in the WYSIWYG CKEditor, and saved as an HTML file in the previously configured saved templates folder. In order to pass template validation, the syntax instruction must be followed. Invalid templates will be saved under "{template name}_{project id} - INVALID.html" and can be edited but not filled, and errors will be returned to the user. Valid templates will be saved, and the user will be redirected to the module index.

Users may add optional footers and headers that follow the same syntax rules as the body.

Users may upload and browse images. However, no security is placed on what images are uploaded, and all images uploaded to the project are viewable by every user. Consider this before uploading.

The user may copy-paste pre-formatted fields and events into their template. Descriptive text fields are exempt from the list. 

## Deleting a template

Users must have access to data exports and reports in order to delete a template. 

## Filling and Downloading Template

The module users the PHP template engine, Smarty, to fill in the templates with the apprpriate record data. Smarty will compile the template and store it in the previously configured compiled templates folder. The user is free to make edits to the template content before downloading. When the template is downloaded, if saving templates to the file repository has been configured, then the module will do so.

##  Web Application Load Balancing

WARNING:  This module is not currently able to support REDCap instances using load balancers due to the requirement to save templates to the file system.

##  Changelog
* v4.1.4
  * bug fixes for backward compatibility, as well as addressing a bug with ampersands in filenames
  * required array value type checking done by in_array() to true (so '12.4' and 12.4 are not considered a match)
* v4.1.3
  * upgraded packages to address security isues:
    * smarty/smarty (v5.1.0 => v5.3.0)
  * backward compatibility fix for pre v14 versions of REDCap (without access to getDataTables() function)
  * bug fix for Smarty "class not found" during template creation; implemented lazy loading.
* v4.1.2
  * Basic date formatting through Smarty; can format with dd-mm-yyyy formatting by passing formatting information through the template as ```{$redcap['visit_date']|date_format:'%d-%m-%Y'}``` This will format the visit_date into two digit day, two digit month, four digit year. **Please note**: this is currently the only other format allowed outside the default REDCap display.
  * Added code to process the new format returned by REDCap::getUserRightsdata()
  * Fixed bug found when downloading a zip file of batched reports (modified headers set before download)
  * Modified code to store data into the proper redcap_dataX tables introduced in RC v14.0.
  * Upgrading packages to address security issues:
    * dompdf/dompdf (v2.0.3 => v3.0.0)
      * php-font-lib (0.5.4 => 1.0.0)
      * php-svg-lib (0.5.0 => 1.0.0)
    * masterminds/html5 (2.8.1 => 2.9.0)
    * sabberworm/php-css-parser (8.4.0 => v8.5.1)
    * smarty/smarty (v4.3.4 => v5.1.0)
  * New packages installed per upgrades above:
    * polyfill-mbstring (v1.29.0)
* v4.0.0
  * Minimal REDCap version is v12.4
  * Implemented support for the RC v12.4+ instrument-level data export permissions.
  * Upgraded Javascript Libraries
    * ckeditor to dev-full/4.21.x
    * smarty to 4.3.4
* v3.2.1
  * upgraded bootstrap-selector to 1.15.0-beta3 to solve bug where records would not display in pulldown when filling templates
  * set maximum REDCap version to 12.1.x
  * Upgrading Javascript libraries
    * masterminds/html5 to 2.8.1
    * smarty/smarty to v4.3.2
* v3.2.0
  * Made compatible with PHP 8, tested with PHP 8.1.
  * Defined EM config.json minimal compatible PHP version as 7.4.0
  * upgraded included Javascript libraries:
    * ckeditor to latest v4.21
    * dompdf to latest v2.0
    * smarty to latest v4.1

