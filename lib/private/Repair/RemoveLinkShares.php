<?php
/**
 * @copyright Copyright (c) 2019, Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OC\Repair;

use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class RemoveLinkShares implements IRepairStep {
	/** @var IDBConnection */
	private $connection;
	/** @var IConfig */
	private $config;

	public function __construct(IDBConnection $connection, IConfig $config) {
		$this->connection = $connection;
		$this->config = $config;
	}


	public function getName() {
		return 'Repair share links';
	}

	private function shouldRun() {
		$versionFromBeforeUpdate = $this->config->getSystemValue('version', '0.0.0');

		if (version_compare($versionFromBeforeUpdate, '14.0.11', '<')) {
			return true;
		}
		if (version_compare($versionFromBeforeUpdate, '15.0.8', '<')) {
			return true;
		}
		if (version_compare($versionFromBeforeUpdate, '16.0.0', '<=')) {
			return true;
		}

		return false;
	}

	/**
	 * @suppress SqlInjectionChecker
	 */
	private function repair() {
		$sql = 'DELETE FROM `*PREFIX*share`
		WHERE `id` IN (
			SELECT `s1.id`
			FROM (
				SELECT *
				FROM `*PREFIX*share`
				WHERE `parent` IS NOT NULL
				AND `share_type` = 3
			) AS s1
			JOIN ``*PREFIX*share`` AS s2
			ON `s1.parent` = `s2.id`
			WHERE (`s2.share_type` = 1 OR `s2.share_type` = 2)
			AND `s1.item_source` = `s2.item_source`
		)';
		$this->connection->executeQuery($sql);
	}

	public function run(IOutput $output) {
		if ($this->shouldRun()) {
			$this->repair();
			$output->info('Removed potentially over exposing link shares');
		} else {
			$output->info('No need to remove link shares.');
		}
	}
}
