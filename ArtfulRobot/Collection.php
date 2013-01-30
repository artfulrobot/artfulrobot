<?php
namespace ArtfulRobot;

// class ARL_Collection implements Iterator
/** handles a collection of objects, iterateable 
 *  basically, this is a wrapper for php's array, but without ever returning
 *  the array itself
 */
class Collection implements \Iterator, \Countable
{
	/** Iterator index */
	private $position = 0;

	/** object that owns this collection */
	public $owner;

	/** main storage object*/
	protected $objects=array();

	/** id to index key */
	protected $id_index=array();

	/** object Id field for primary index */
	protected $id='id';

	public function __construct($owner=null) 
	{
		$this->rewind();
		$this->owner = $owner;
	}

    public function rewind() 
	{
        $this->position = 0;
    }

    public function count() 
	{
        return count($this->objects);
    }

    public function current() 
	{
        return $this->objects[$this->position]['object'];
    }

    public function key() 
	{
        return $this->position;
    }

    public function next() 
	{
        ++$this->position;
    }

    public function valid() 
	{
        return isset($this->objects[$this->position]);
    }

	public function append($object,$id=null)
	{
		if ($id) {
			if (isset($this->id_index[$id])) throw new Exception( "Attempted to add object $id which is already in collection");
		}
		$this->id_index[$id] = count($this->objects);
		$this->objects[] = array( 'id' => $id, 'object' => $object );

		// tell owner it's been added: owner might want to force something on the new object
	}
	// public function find( $criteria, $findNext=false ) {{{
    /** search for the next object in the collection that matches criteria
      * 
      * $criteria = Array( prop=>val [,...])
      * $findNext : normally start from first element, set true for findNext.
      */
	public function find( $criteria, $findNext=false )
	{
        if (!$findNext) $this->rewind();
        while ($o = $this->current($this)) {
            $this->next();
            $match = true;
            foreach ($criteria as $key=>$val) {
                if ($o->$key != $val) { 
                    $match = false;
                    break;
                }
            }
            if ($match) return $o;
        }
        return null;
	}/*}}}*/
	public function removeById($id)
	{
		if (!isset($this->id_index[$id])) throw new Exception( "Attempted to remove object $id which is not in collection");
		$index = $this->id_index[$id];
		$this->removeByIndex($index);
	}
	public function removeByIndex($index)
	{
		if (!isset($this->objects[$index])) throw new Exception( "Attempted to removeByIndex at $index but item not found.");

		// update id_index
		$l = count($this->objects);
		for($i=$index+1;$i<$l;$i++) {
			if ($id = $this->objects[$i]['id']) {
				$this->id_index[$id]--;
			}
		}
		if ($id = $this->objects[$index]['id']) unset($this->id_index[$id]);
		unset($this->objects[$index]);
	}
}
