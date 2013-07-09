<?php
namespace ArtfulRobot;

//class PDO_Query
/** class to add features to standard sql queries, used by various \ArtfulRobot\PDO methods
 * 
 * For readability this class insists on a comment for the sql that should
 * explain what's supposed to be achived by the SQL
 *
 * sql and optionally, array of named parameters are self-explanatory.
 *
 * However, this class also imlpemements prepared statements for arrays of data
 * on the input parameters.
 *
 * Example: fetch a number of specified records:
 *
 * $records = $my_\ArtfulRobot\PDO->fetch_rows_assoc( new \ArtfulRobot\PDO_Query(
 *  "Fetch records 1,2,5",
 * 	"SELECT * FROM table WHERE id IN (:id_list) AND lname = :lname"
 * 	array( ':id_list' => array(1,2,5) ) ) );
 */ 
class PDO_Query
{
	private $comment;
	private $sql;
	private $params;
	private $array_params;
	function __construct($comment, $sql, $params=null, $has_array_params='deprecated')/*{{{*/
	{
		$this->comment = $comment;
		$this->sql     = $sql;
		$this->params  = $params;

		if ( ! is_array($params) ) return;

		// scan params for array values
		foreach ($this->params as $key=>$value) {
			if (!is_array($value)) continue;

			$c = count($value);
			if ($c==0) {
                $this->params[$key] = null;

            } elseif ($c==1) {
                $this->params[$key] = reset($value);

            } else {
				$i=0;
				$replacement_params = array();
				while( count($value)>0 ) {
                    $single_val = array_shift($value);
					$single_key = $key . "__" . $i++;
					$this->params[$single_key] = $single_val;
					$replacement_params[] = $single_key;
				}
				$this->sql = preg_replace("/" . preg_quote($key,'/') . '\b/', implode(", ", $replacement_params), $this->sql);
				// remove original parameter
				unset($this->params[$key]);
			}
		}
	}/*}}}*/
	public function __get($field)/*{{{*/
	{
		if ($field == 'comment' || $field == 'sql' || $field =='params' ) return $this->$field;
		throw new Exception("Unknown property '$field' in class " . __CLASS__ );
	}/*}}}*/

	public function __toString()
	{
		return "\\ArtfulRobot\PDO_Query: \n"
			. strtr($this->sql, array("\t"=>"  "))
			. "\nParams:\n" 
			. print_r($this->params,1);
	}
}

