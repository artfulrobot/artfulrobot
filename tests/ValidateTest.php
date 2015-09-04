<?php
/**
 * @file
 *
 * Unit tests for Validate and ValidateItem classes.
 *
 * @copyright 2015 Rich Lott / Artful Robot
 * @licence GPL3+
 */
use \ArtfulRobot\Validate;
use \ArtfulRobot\ValidateItem;


class ValidateTest extends \PHPUnit_Framework_TestCase {

  /**
   * Test defaults
   */
  public function testDefaults() {
    $v = new Validate(['id'=>'']);
    $this->assertEquals('foo', $v->aMissingField->defaultIfMissing('foo')->get());
    $this->assertEquals('foo', $v->anotherMissingField->defaultIfEmpty('foo')->get());
    $this->assertEquals('', $v->id->defaultIfMissing('foo')->get());
    $this->assertEquals('foo', $v->id->defaultIfEmpty('foo')->get());
  }
  /**
   * @expectedException \InvalidArgumentException
   * @expectedExceptionMessage foo missing.
   */
  public function testRequiredMissing() {
    $v = new Validate(['date'=>'', 'id'=>'']);
    $v->foo->required();
  }

  /**
   */
  public function testRequiredExists() {
    $v = new Validate(['date'=>'', 'id'=>'']);
    $r = $v->date->required();
    $this->assertInstanceOf('\ArtfulRobot\ValidateItem', $r);
  }

  /**
   * @expectedException \InvalidArgumentException
   * @expectedExceptionMessage date is an invalid date.
   */
  public function testDateParseEmpty() {
    $v = new Validate(['date'=>'', 'id'=>'']);
    $v->date->validDate();
  }

  /**
   */
  public function testDateParseEmptyAllowed() {
    $v = new Validate(['date'=>'', 'id'=>'']);
    $r = $v->date->allowEmpty()->validDate();
    $this->assertInstanceOf('\ArtfulRobot\ValidateItem', $r);
  }
  /**
   */
  public function testDateParseValid() {
    $v = new Validate(['date'=>'20 Jul 2007', 'id'=>'']);
    $r = $v->date->validDate();
    $this->assertInstanceOf('\ArtfulRobot\ValidateItem', $r);
    $this->assertEquals('2007-07-20', (string) $r);
    $this->assertEquals('2007-07-20', $r());
  }
  /**
   * @expectedException \InvalidArgumentException
   * @expectedExceptionMessage date is an invalid date.
   */
  public function testDateParseInvalid() {
    $v = new Validate(['date'=>'toady', 'id'=>'']);
    $r = $v->date->validDate();
    $this->assertInstanceOf('\ArtfulRobot\ValidateItem', $r);
  }
  /**
   */
  public function testSet() {
    $v = new Validate(['date'=>'', 'id'=>'']);
    $r = $v->foo->set('bar');
    $this->assertInstanceOf('\ArtfulRobot\ValidateItem', $r);
    $this->assertEquals('bar', $r());
  }
  /**
   */
  public function testEmpty() {
    $v = new Validate(['date'=>'']);
    $this->assertEquals('today', (string) $v->date->allowEmpty('today'));
    $this->assertEquals('', (string) $v->foo);
    $this->assertNull($v->foo->get());
  }
  /**
   * @expectedException \InvalidArgumentException
   * @expectedExceptionMessage id must be above 1
   */
  public function testInt() {
    $v = new Validate(['id'=>-1]);
    $v->id->integer(1);
  }
  /**
   * @expectedException \InvalidArgumentException
   * @expectedExceptionMessage id must be above 1
   */
  public function testIntWithString() {
    $v = new Validate(['id'=>'foo']);
    $v->id->integer(1);
  }
}


