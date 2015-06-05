<?php

namespace Unish;

/**
 * Tests for Configuration Management commands for D8+.
 * @group commands
 */
class ConfigCase extends CommandUnishTestCase {

  function setUp() {
    if (UNISH_DRUPAL_MAJOR_VERSION < 8) {
      $this->markTestSkipped('Config only available on D8+.');
    }

    if (!$this->getSites()) {
      $this->setUpDrupal(1, TRUE);
    }
  }

  function testConfigGetSet() {
    $options = $this->options();
    $this->drush('config-set', array('system.site', 'name', 'config_test'), $options);
    $this->drush('config-get', array('system.site', 'name'), $options);
    $this->assertEquals("'system.site:name': config_test", $this->getOutput(), 'Config was successfully set and get.');
  }

  function testConfigList() {
    $options = $this->options();
    $this->drush('config-list', array(), $options);
    $result = $this->getOutputAsList();
    $this->assertNotEmpty($result, 'An array of config names was returned.');
    $this->assertTrue(in_array('update.settings', $result), 'update.settings name found in the config names.');

    $this->drush('config-list', array('system'), $options);
    $result = $this->getOutputAsList();
    $this->assertTrue(in_array('system.site', $result), 'system.site found in list of config names with "system" prefix.');

    $this->drush('config-list', array('system'), $options + array('format' => 'json'));
    $result = $this->getOutputFromJSON();
    $this->assertNotEmpty($result, 'Valid, non-empty JSON output was returned.');
  }

  function testConfigExportImport() {
    $options = $this->options();
    // Get path to staging dir.
    $this->drush('core-status', array(), $options + array('format' => 'json'));
    $staging = $this->webroot() . '/' . $this->getOutputFromJSON('config-staging');
    $system_site_yml = $staging . '/system.site.yml';
    $core_extension_yml = $staging . '/core.extension.yml';

    // Test export
    $this->drush('config-export', array(), $options);
    $this->assertFileExists($system_site_yml);

    // Test import by finish the round trip.
    $contents = file_get_contents($system_site_yml);
    $contents = str_replace('front: user', 'front: unish', $contents);
    $contents = file_put_contents($system_site_yml, $contents);
    $this->drush('config-import', array(), $options);
    $this->drush('config-get', array('system.site', 'page'), $options + array('format' => 'json'));
    $page = $this->getOutputFromJSON('system.site:page');
    $this->assertContains('unish', $page->front, 'Config was successfully imported.');

    $this->drush('pm-enable', array('tracker'), $options);
    $module_adjustments = array('module-adjustments' => 'tracker');

    // Run config-import again, but filter 'tracker' into the configuration.
    // It is not presently in the exported configuration, because we enabled
    // it after export.  If we imported again without adding 'tracker' with
    // 'module-adjustments', then it would be disabled.
    $this->drush('config-import', array(), $options + $module_adjustments);
    $this->drush('config-get', array('core.extension', 'module'), $options + array('format' => 'yaml'));
    $modules = $this->getOutput();
    $this->assertEquals('tracker', $modules, 'Tracker module found in extension list after import.');

    // Run config-export again - note that 'tracker' is enabled, but we
    // are going to filter it out on write, so no changes should be written
    // to core.extension when it is exported.
    $this->drush('config-export', array(), $options + $module_adjustments);
    $this->assertFileExists($core_extension_yml);
    $contents = file_get_contents($core_extension_yml);
    $this->assertNotContains('tracker', $contents);

    // Run config-export one final time.  'tracker' is still enabled, even
    // though it was filtered out, and therefore not exported last time.
    // When we remove the module-adjustments option, then 'tracker' will
    // be exported.
    $this->drush('config-export', array(), $options);
    $this->assertFileExists($core_extension_yml);
    $contents = file_get_contents($core_extension_yml);
    $this->assertContains('tracker', $contents);
  }

  /**
   * Tests editing config from a file (not interactively).
   */
  public function testConfigEdit() {
    // Write out edits to a file.
    $config = "name: 'TEST NAME'\nmail: test@testmail.example.org";
    $path = UNISH_SANDBOX . '/system.site.yml';
    file_put_contents($path, $config);

    $options = $this->options();
    $options += array(
      'file' => $path,
      'yes' => NULL,
    );
    $this->drush('config-edit', array(), $options);
    $this->drush('config-get', array('system.site'), $this->options());
    $this->assertEquals($config, $this->getOutput());
  }

  function options() {
    return array(
      'yes' => NULL,
      'root' => $this->webroot(),
      'uri' => key($this->getSites()),
    );
  }
}
