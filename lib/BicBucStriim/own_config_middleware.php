<?php

require 'vendor/autoload.php';
require_once 'lib/BicBucStriim/bicbucstriim.php';
require_once 'lib/BicBucStriim/bbs_pdo.php';
use Strong\Strong;

class OwnConfigMiddleware extends \Slim\Middleware {

	protected $knownConfigs;

	/**
     * Initialize the configuration
     *
     * @param array $config
     */
    public function __construct($knownConfigs) {
        $this->knownConfigs = $knownConfigs;
    }

	public function call() {
		global $globalSettings;
		$app = $this->app;
		$config_status = $this->check_config_db();
		if ($config_status == 0) {
			// TODO severe error message + redirect to installcheck.php
			$app->halt(500, 'No or bad configuration database. Please use < href="'.
				$app->request->getRootUri().
				'/installcheck.php">installcheck.php</a> to check for errors.');
		} elseif ($config_status == 2) {
			// TODO severe error message + redirect to installcheck.php
			$app->halt(500, 'Old configuration database detected. Please use < href="'.
				$app->request->getRootUri().
				'/update.php">update.php</a> to update the DB structure.');
		} else {
			$this->next->call();
		}

	}

	protected function check_config_db() {
		global $globalSettings, $we_have_config;
		$we_have_config = 0;
		$app = $this->app;
		if ($app->bbs->dbOk()) {
			$we_have_config = 1;
			$css = $app->bbs->configs();
			foreach ($css as $config) {
				if (in_array($config->name, $this->knownConfigs)) 
					$globalSettings[$config->name] = $config->val;
				else 
					$app->getLog()->warn(join('own_config_middleware: ',
						array('Unknown configuration, name: ', $config->name,', value: ',$config->val)));	
			}

			## For 1.0: run a silent db update
			# TODO post 1.0: replace with an updater 
			if ($globalSettings[DB_VERSION] != DB_SCHEMA_VERSION) {
				$app->getLog()->warn('own_config_middleware: old db schema detected. please run update');							
				return 2;
			}
			
			if (!isset($app->strong)) 
				$app->strong = $this->getAuthProvider($app->bbs->mydb);
			$app->getLog()->debug("own_config_middleware: config loaded");
		} else {
			$app->getLog()->info("own_config_middleware: no config db found - creating a new one with default values");
			$app->bbs->createDataDb();
			$app->bbs = new BicBucStriim('data/data.db', 'data');
			$cnfs = array();
			foreach($this->knownConfigs as $name) {
				$cnf = R::dispense('config');
				$cnf->name = $name;
				$cnf->val = $globalSettings[$name];
				array_push($cnfs, $cnf);
			}
			$app->bbs->saveConfigs($cnfs);
			if (!isset($app->strong)) 
				$app->strong = $this->getAuthProvider($app->bbs->mydb);
			$we_have_config = 1;
		}
		return $we_have_config;
	}

	protected function getAuthProvider($db) {
		$provider = new BBSPDO(array('pdo' => $db));
		return new Strong(array('provider' => $provider, 'pdo' => $db));;
	}
}
?>
