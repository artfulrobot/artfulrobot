<?php
use \ArtfulRobot\CsvParser;

class CsvParserTest extends \PHPUnit_Framework_TestCase {

    public function testBasicParse()
    {
      $csv = new CsvParser();
      $csv->loadFromFile(dirname(__FILE__) . '/fixtures/testcase-1.csv');
      $i=0;
      $expectations = [['Rich',  40], ['Fred',  1000], ['Wilma',  ''], ['',  56], ['Bam Bam', '']];
      foreach ($csv as $row) {
        $expect = array_shift($expectations);
        $this->assertEquals($expect[0], $row->Name);
        $this->assertEquals($expect[1], $row->Age);
        $i++;
      }
      $this->assertEquals(5, $i, "Expected 4 records, got $i");
    }


    public function testRandomAccess()
    {
      $csv = new CsvParser();
      $csv->loadFromFile(dirname(__FILE__) . '/fixtures/testcase-1.csv');
      $this->assertEquals('Rich', $csv->Name);
      $this->assertEquals('Rich', $csv->getCell(0));
      $this->assertEquals('Rich', $csv->getCell(0,1));
      $this->assertEquals('Fred', $csv->getCell(0,2));
    }
    public function testMultiLine()
    {
      $csv = new CsvParser();
      $csv->loadFromFile(dirname(__FILE__) . '/fixtures/testcase-2.csv');
      $i=0;
      $expectations = [['Fred',  "The Cave\nHovelshire"], ['Betty',  'Elsewhere']];
      foreach ($csv as $row) {
        $expect = array_shift($expectations);
        $this->assertEquals($expect[0], $row->Name);
        $this->assertEquals($expect[1], $row->Address);
        $i++;
      }
      $this->assertEquals(2, $i, "Expected 2 records, got $i");
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Duplicate header name: Name
     */
    public function testDodgyHeaders()
    {
      $csv = new CsvParser();
      $csv->loadFromFile(dirname(__FILE__) . '/fixtures/testcase-3-dodgy-headers.csv');
    }
}


