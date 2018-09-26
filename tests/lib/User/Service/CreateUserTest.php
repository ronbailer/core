<?php
/**
 * @author Sujith Haridasan <sharidasan@owncloud.com>
 *
 * @copyright Copyright (c) 2018, ownCloud GmbH
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace Test\User\Service;

use OC\User\Service\CreatePassword;
use OC\User\Service\CreateUser;
use OC\User\Service\UserSendMail;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\ILogger;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Mail\IMailer;
use OCP\Security\ISecureRandom;
use Test\TestCase;

class CreateUserTest extends TestCase {
	/** @var IUserSession | \PHPUnit_Framework_MockObject_MockObject */
	private $userSession;
	/** @var IGroupManager | \PHPUnit_Framework_MockObject_MockObject */
	private $groupManager;
	/** @var IUserManager | \PHPUnit_Framework_MockObject_MockObject */
	private $userManager;
	/** @var IMailer | \PHPUnit_Framework_MockObject_MockObject */
	private $mailer;
	/** @var ISecureRandom | \PHPUnit_Framework_MockObject_MockObject */
	private $secureRandom;
	/** @var ILogger | \PHPUnit_Framework_MockObject_MockObject */
	private $logger;
	/** @var UserSendMail | \PHPUnit_Framework_MockObject_MockObject */
	private $userSendMail;
	/** @var CreatePassword | \PHPUnit_Framework_MockObject_MockObject */
	private $createPassword;
	/** @var CreateUser */
	private $createUser;

	protected function setUp() {
		parent::setUp();

		$this->userSession = $this->createMock(IUserSession::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->mailer = $this->createMock(IMailer::class);
		$this->secureRandom = $this->createMock(ISecureRandom::class);
		$this->logger = $this->createMock(ILogger::class);
		$this->userSendMail = $this->createMock(UserSendMail::class);
		$this->createPassword = $this->createMock(CreatePassword::class);
	}

	public function testCreateUserWithEmail() {
		$this->mailer->method('validateMailAddress')
			->willReturn(true);
		$currentUser = $this->createMock(IUser::class);
		$currentUser->method('getUID')
			->willReturn('user1');
		$newUser = $this->createMock(IUser::class);
		$newUser->method('getUID')
			->willReturn('foo');

		$this->userSession->method('getUser')
			->willReturn($currentUser);
		$this->groupManager->method('isAdmin')
			->willReturn(true);

		$this->userManager->method('userExists')
			->with('foo')
			->willReturn(false);

		$this->secureRandom->method('generate')
			->willReturn('aBcDeFgH');

		$this->userManager->method('createUser')
			->willReturn($newUser);

		$group = $this->createMock(IGroup::class);
		$this->groupManager->method('get')
			->willReturn($group);

		$this->createUser = $this->getMockBuilder(CreateUser::class)
			->setConstructorArgs([$this->userSession, $this->groupManager,
				$this->userManager, $this->mailer, $this->secureRandom, $this->logger,
				$this->userSendMail, $this->createPassword])
			->setMethods(['triggeredFromCommandLine'])
			->getMock();

		$result = $this->createUser->createUser('foo', '', ['group1'], 'foo@bar.com');
		$this->assertInstanceOf(IUser::class, $result);
		$this->assertEquals('foo', $result->getUID());
	}
}
