<?php
use \ArtfulRobot\Gravatar;

class GravatarTest extends \PHPUnit_Framework_TestCase {

  public function testFindsCorrect() {
    $url = Gravatar::factory()
      ->getFirstUrl(['hasnogravatar@artfulrobot.uk', 'forums@artfulrobot.uk']);
    $this->assertEquals('https://www.gravatar.com/avatar/ebb611061d9881eb1b6af3d3c9a696a7?s=80', $url);
  }
  public function testMissing() {
    $url = Gravatar::factory()
      ->getFirstUrl(['hasnogravatar@artfulrobot.uk']);
    $this->assertEquals('', $url);
  }
}


