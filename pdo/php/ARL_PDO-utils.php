<?php
/** creates PHP model given connection and tablename
  * Synopsis: ARL_PDO_Model_Creator::go( PDO $pdo, 'mytable');
  */
class ARL_PDO_Model_Creator
{
	public static function go(PDO $pdo, $tablename)
	{
		$stmt = $pdo->query("SHOW FIELDS FROM $tablename");
		$rows = $stmt->fetchAll(PDO::FETCH_OBJ);
		//if (!$rows) throw new Exception("Could not stat '$tablename'");

		$max = 0;
		foreach ($rows as $row)
			if (($_ = strlen($row->Field))>$max) $max = $_;
		$max+=4;

		$def = array();

		foreach ($rows as $row)
		{
			$line = sprintf("%-{$max}s", "'$row->Field'")
				."=> array( ";
			//cast?
			$t = strtolower($row->Type);
			if (preg_match('/^(?:tiny|long|med|big|)int\((\d+)\)\s?(unsigned)?/', $t, $matches))
			{
				$line .= "'cast' => 'int"
					. ($matches[2]  ? "_unsigned'" 
									: "'         ")
					. ($matches[1] ? ", 'size' => $matches[1] " : "");
			}
			elseif ($t == 'datetime')
				$line .= "'cast' => 'datetime'    ";
			elseif ($t == 'timestamp')
				$line .= "'cast' => 'datetime'    ";
			elseif ($t == 'date')
				$line .= "'cast' => 'date'        ";
			elseif ($t == 'time')
				$line .= "'cast' => 'time'        ";
			elseif ($t == 'decimal'
				|| $t == 'double'
				|| $t == 'float'
				|| $t == 'numeric')
				$line .= "'cast' => 'float'        ";
			elseif (preg_match('/^(?:var)?char\((\d+)\)/', $t, $matches))
				$line .= "'cast' => 'string'      "
					. ($matches[1] ? ", 'size' => $matches[1] ":"");
			elseif (preg_match('/^text/', $t, $matches))
				$line .= "'cast' => 'string'      , 'size' => 65535";
			elseif (preg_match('/^longtext/', $t, $matches))
				$line .= "'cast' => 'string'      "; // limit is 4Gb!
			elseif (preg_match('/^enum\((.+)\)/', $t, $matches))
				$line .= "'cast' => 'enum'        , 'enum' => array( $matches[1] )";

			//null?
			$line .= ", 'null' => " . ($row->Null == 'YES' ? 'TRUE ' : 'FALSE' );

			//default?
			$line .= ", 'default' => "
				. ($row->Default == 'NULL'
					? 'NULL'
					: ($row->Default == 'CURRENT_TIMESTAMP'
						? "'now'" /* this is not valid sql, but it will be parsed by php's strtotime(), and it will work there. */
						: "'$row->Default'"));

			$def[] = "$line )";
		}
		$def = implode(",\n\t\t", $def);

		$tmp = explode("_", $tablename);
		$tmp = array_map('ucfirst', $tmp);
		
		$classname = implode('_', $tmp);

		$out =  <<<PHP

//class Model_$classname extends ARL_PDO_Model {{{
/** ARL_PDO_Model for $classname
  */
class Model_$classname extends ARL_PDO_Model
{
	protected \$TABLE_NAME = '$tablename';
	protected \$definition = array(
		$def);
	protected function getter(\$name) {}
	protected function setter(\$name, \$value) {}
	/** this function must initialise an ARL_PDO object in \$this->conn */
	abstract protected function db_connect();
} // }}}

PHP;
	echo highlight_string( '<?php ' . $out, true );
	}
}
