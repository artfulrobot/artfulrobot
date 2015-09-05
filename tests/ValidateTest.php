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
    $this->assertEquals('foo', $v->aMissingField->defaultIfMissing('foo')->v);
    $this->assertEquals('foo', $v->anotherMissingField->defaultIfEmpty('foo')->v);
    $this->assertEquals('', $v->id->defaultIfMissing('foo')->v);
    $this->assertEquals('foo', $v->id->defaultIfEmpty('foo')->v);
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
    $v->date->castToDate();
  }

  /**
   */
  public function testDateParseEmptyAllowed() {
    $v = new Validate(['date'=>'', 'id'=>'']);
    $r = $v->date->allowEmpty()->castToDate();
    $this->assertInstanceOf('\ArtfulRobot\ValidateItem', $r);
  }
  /**
   */
  public function testDateParseValid() {
    $v = new Validate(['date'=>'20 Jul 2007', 'id'=>'']);
    $r = $v->date->castToDate();
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
    $r = $v->date->castToDate();
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
    $this->assertNull($v->foo->v);
  }
  /**
   * @expectedException \InvalidArgumentException
   * @expectedExceptionMessage id must be above 1
   */
  public function testInt() {
    $v = new Validate(['id'=>-1]);
    $v->id->castToInt(1);
  }
  /**
   * @expectedException \InvalidArgumentException
   * @expectedExceptionMessage id must be above 1
   */
  public function testIntWithString() {
    $v = new Validate(['id'=>'foo']);
    $v->id->castToInt(1);
  }
  /**
   * @expectedException \InvalidArgumentException
   * @expectedExceptionMessage id is not one of the allowed values.
   */
  public function testPatternArrayFail() {
    $v = new Validate(['id'=>'foo']);
    $v->id->matches(['bar', 'bad']);
  }
  public function testPatternArrayPass() {
    $v = new Validate(['id'=>'foo']);
    $v->id->matches(['bar', 'bad', 'foo']);
  }
  /**
   * @expectedException \InvalidArgumentException
   * @expectedExceptionMessage id is not as expected
   */
  public function testPatternRegexFail() {
    $v = new Validate(['id'=>'foo']);
    $v->id->matches('/^goo/');
  }
  public function testPatternRegexPass() {
    $v = new Validate(['id'=>'foo']);
    $v->id->matches('/^foo/');
  }
  public function testPropCasting() {
    $v = new Validate(['id'=>'45','check'=>'checked', 'date'=>'today', 'i'=>10]);
    $this->assertInternalType('int', $v->i->int);
    $this->assertEquals(10, $v->i->int);
    $this->assertInternalType('string', $v->i->string);
    $this->assertEquals('10', $v->i->string);

    $this->assertInternalType('int', $v->id->int);
    $this->assertEquals(45, $v->id->int);
    $this->assertInternalType('string', $v->id->value);
    $this->assertEquals(45, $v->id->value);
    $v->id->castToInt();
    $this->assertInternalType('int', $v->id->value);
    $this->assertEquals(45, $v->id->value);
    $this->assertInternalType('int', $v->id->int);
    $this->assertEquals(45, $v->id->int);
    $this->assertInternalType('string', $v->id->string);
    $this->assertEquals('45', $v->id->string);

    $this->assertInternalType('bool', $v->misingcheckbox->bool);
    $this->assertEquals(FALSE, $v->misingcheckbox->bool);
    $this->assertInternalType('bool', $v->check->bool);
    $this->assertEquals(TRUE, $v->check->bool);
  }
  /**
   * the required() method throws an exception if the value is missing.
   *
   * We should be able to satisfy required() by setting a default value,
   * and by telling it to allowEmpty.
   */
  public function testRequiredHappyAfterDefault() {
    $v = new Validate(['zls' => '']);
    $this->assertInstanceOf('\ArtfulRobot\ValidateItem',
      $v->foo->defaultIfMissing('bar')->required());

    $this->assertInstanceOf('\ArtfulRobot\ValidateItem',
      $v->bar->allowEmpty()->required());

    // ZLS is valid
    $this->assertInstanceOf('\ArtfulRobot\ValidateItem',
      $v->zls->required());
  }
  /**
   * the notEmpty() method throws an exception if the value is empty.
   *
   * @expectedException \InvalidArgumentException
   * @expectedExceptionMessage foo required
   */
  public function testNotEmptyFailsIfMissing() {
    $v = new Validate(['zls' => '']);
    $v->foo->notEmpty();
  }
  /**
   * the notEmpty() method throws an exception if the value is empty.
   *
   * @expectedException \InvalidArgumentException
   * @expectedExceptionMessage zls required
   */
  public function testNotEmptyFailsIfZls() {
    $v = new Validate(['zls' => '']);
    $v->zls->notEmpty();
  }
  /**
   * the notEmpty() method throws an exception if the value is empty - inc 0.
   *
   * This is slightly odd, but it's the way PHP'e empty() works, so we stick
   * with it.
   *
   * @expectedException \InvalidArgumentException
   * @expectedExceptionMessage zero required
   */
  public function testNotEmptyFailsIfZero() {
    $v = new Validate(['zero'=>0]);
    $v->zero->notEmpty();
  }
  /**
   * the notEmpty() method throws an exception if the value is empty.
   *
   * We should be able to satisfy required() by setting a default value,
   * but not by telling it to allowEmpty.
   *
   * @expectedException \InvalidArgumentException
   * @expectedExceptionMessage bar required.
   */
  public function testNotEmpty() {
    $v = new Validate(['zls' => '']);

    // setting default should be Ok
    $this->assertInstanceOf('\ArtfulRobot\ValidateItem',
      $v->foo->defaultIfMissing('bar')->notEmpty());

    // this should throw exception.
    $this->assertInstanceOf('\ArtfulRobot\ValidateItem',
      $v->bar->allowEmpty()->notEmpty());
  }
}


