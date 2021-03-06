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
   * Provides postcodes to test.
   */
  public function postcodeProvider() {
    return [
      ['OX4 2HZ'    , 'OX4 2HZ'  , 'valid control']         ,
      ['ox4 2HZ'    , 'OX4 2HZ'  , 'valid lowercase']       ,
      [' OX4 2HZ '  , 'OX4 2HZ'  , 'pre/post spaces']       ,
      ['OX4  2HZ'   , 'OX4 2HZ'  , 'multiple spaces']       ,
      ['OX4  2 HZ'  , 'OX4 2HZ'  , 'extra space']           ,
      ['OX4  2 H Z' , 'OX4 2HZ'  , 'spaces everywhere']     ,
      ['OX42HZ'     , 'OX4 2HZ'  , 'missing space']         ,
      ['OX 42 HZ'   , 'OX4 2HZ'  , 'spaces around numbers'] ,
      ['NR322QG'    , 'NR32 2QG' , 'real1']                 ,
      ['BA140AL'    , 'BA14 0AL' , 'real2']                 ,
      ['BS24 8 EH'  , 'BS24 8EH' , 'real3']                 ,
      ['bd184rz'    , 'BD18 4RZ' , 'real4']                 ,
      ['YO243XN'    , 'YO24 3XN' , 'real5']                 ,
      ['CF241AA'    , 'CF24 1AA' , 'real6']                 ,
      ['RM141ER'    , 'RM14 1ER' , 'real7']                 ,
    ];
  }
  /**
   * @dataProvider postcodeProvider
   */
  public function testPostcodes($given, $expected, $message) {
    $v = new Validate(['p'=>$given]);
    $this->assertEquals($expected, (string) $v->p->castToUKPostcode(), "Failed on: $message");
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

    $this->assertInstanceOf('\ArtfulRobot\ValidateItem',
      $v->b->set($v->foo));
    $this->assertEquals('bar', $v->b->value);
  }
  /**
   */
  public function testRaw() {
    $v = new Validate(['date'=>'2007-07-20 + 1 day']);
    $v->date->castToDate();
    $this->assertEquals('2007-07-20 + 1 day', $v->date->raw);
    $this->assertEquals('2007-07-21', $v->date->string);
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


