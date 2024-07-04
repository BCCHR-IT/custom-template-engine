<?php
/*
** ExportRights.php - a script to define a data export rights glass for use in REDCap v13+.
**
** Description: before v13, data export rights in RC had a single 0,1,2 value. As of v13, 
**	the rights scheme changes to 0/1/2/3 values on a per-instrument basis stored as a 
**	string; tuples are stored as [_instrument_,_rights_value_], and concatenated 
**	together without spaces. This data is stored  under the key 'data_export_instruments'.
**  Value meanings (as of v13.7) are: 0 = no rights, 1 = full rights, 2 = de-identified rights, 
**  3 = remove all identifier fields.
**
**	This script will define a class to allow for this finer-grained export control. It 
**	will store both the raw rights object, as well as a parsed version of the 
**	per-instrument rights which include field-level rights.
**
** Assumptions: if the rights are stored not as a string in later version, the parseRights() 
**  function will need to be rewritten. Further, if the rights values are changed or added to, 
**  any logic that uses an ExportRights object may need to be modified.
*/
namespace BCCHR\CustomTemplateEngine;

use REDCap;  // to get field names for a project

class ExportRights {

	private $raw_rights = array();  // raw rights information from a getUserRights call
	private $instruments = array();  // an array of instrument names for this PID
	private $field_names = array();  // an array of field names for this PID
	public $field_to_rights_value = array();  // and associative array, keys are field names, value is 0/1/2

    public function setRights($r) {
    /*
    ** lazy loading call to replace constructor version of setting the rights
    */

        if (!is_array($r) || empty($r)) {

            throw new \InvalidArgumentException("Error: array output from REDCap::getUserRights required.");

        }  // end elseif

        $this->raw_rights = $r;
        $this->parseRights();  // parse the rights content passed in

    }  // end setRights()

    public function __toString() {
    /*
    ** String version of this object
    */
        return print_r($this->field_to_rights_value, true);

    }  // end __toString()

	private function parseRights() {
	/*
	** parses the output of REDCap::getUserRights() into a set of associative arrays defining the rights 
	**  for a user on a per-field basis.
    **
    ** N.B.: This is a likely place to look for issues with rights not being applied properly. The format 
    **  for how rights were stored/returned by the REDCap::getUserRights() call has changed once already.
	*/

        $raw_rights_arr = array();  // filled below

		foreach ($this->raw_rights as $k => $v) {  // outer assoc array is keyed by username, so isolate value

			$rights_arr = $v;
			break;

		}  // end foreach

		foreach ($rights_arr as $key => $value) {  // for each k,v pair in the inner assoc array

			if ($key == 'data_export_instruments') {  // capture the instrument names and their value, pre v14.0-ish, uncertain when it changed.

				$rights_str = str_replace('][', '|', substr($value, 1, -1));  // cut off first and last character and change splitting character
				$raw_rights_arr = explode('|', $rights_str);  // make into an array

			} else if ($key == 'forms_export') {  // v14.0+ version of the rights object (at latest, the above works in v13.7

                $raw_rights_arr = array();
                foreach ($value as $rk => $rv) {

                    array_push($raw_rights_arr, "$rk,$rv");

                }  // end foreach

            }  // end else

			foreach ($raw_rights_arr as $tuple) {  // for every instrument-value tuple

				$rights_tuple = explode(',', $tuple);  // split into array on the comma

				if (!in_array($rights_tuple, $this->instruments, true)) {  // capture instrument names

					array_push($this->instruments, $rights_tuple[0]);

				}  // end if

				$field_array = REDCap::getFieldNames($rights_tuple[0]);  // get all the fields in this instrument

				foreach ($field_array as $field_name) {  // capture field names and their export rights

					if (!in_array($field_name, $this->field_names, true)) {

						array_push($this->field_names, $field_name);
						$this->field_to_rights_value[$field_name] = $rights_tuple[1];

					}  // end if

				}  // end foreach

			}  // end foreach

		}  // end foreach

	}  // end parseRights()

}  // end class


?>
