<?php
declare(strict_types=1);

namespace Digitalway\SocialLogin\Test\Unit\Controller\Dev;

use Digitalway\SocialLogin\Controller\Dev\Simulate;
use Digitalway\SocialLogin\Model\Profile;
use Digitalway\SocialLogin\Model\ProfileLoginFlow;
use Digitalway\SocialLogin\Model\ReturnUrlValidator;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\State as AppState;
use Magento\Framework\Controller\Result\Forward;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class SimulateTest extends TestCase
{
    private AppState&MockObject $appState;
    private ProfileLoginFlow&MockObject $flow;
    private Forward&MockObject $forward;
    private Redirect&MockObject $redirect;
    private Simulate $controller;

    protected function setUp(): void
    {
        $params = ['provider' => 'instagram', 'id' => '555', 'first' => 'mario.shop', 'return' => ''];
        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->willReturnCallback(static fn (string $key) => $params[$key] ?? null);

        $this->appState = $this->createMock(AppState::class);
        $this->flow = $this->createMock(ProfileLoginFlow::class);
        $returnUrlValidator = $this->createMock(ReturnUrlValidator::class);
        $returnUrlValidator->method('sanitize')->willReturn('');

        $this->forward = $this->createMock(Forward::class);
        $this->forward->method('forward')->willReturnSelf();
        $forwardFactory = $this->createMock(ForwardFactory::class);
        $forwardFactory->method('create')->willReturn($this->forward);

        $this->redirect = $this->createMock(Redirect::class);
        $this->redirect->method('setUrl')->willReturnSelf();
        $this->redirect->method('setPath')->willReturnSelf();
        $redirectFactory = $this->createMock(RedirectFactory::class);
        $redirectFactory->method('create')->willReturn($this->redirect);

        $this->controller = new Simulate(
            $this->appState,
            $request,
            $this->flow,
            $returnUrlValidator,
            $redirectFactory,
            $forwardFactory,
            $this->createMock(ManagerInterface::class)
        );
    }

    public function testIsNotReachableOutsideDeveloperMode(): void
    {
        $this->appState->method('getMode')->willReturn(AppState::MODE_PRODUCTION);
        $this->forward->expects(self::once())->method('forward')->with('noroute');
        $this->flow->expects(self::never())->method('complete');

        self::assertSame($this->forward, $this->controller->execute());
    }

    public function testDeveloperModeRunsTheRealFlow(): void
    {
        $this->appState->method('getMode')->willReturn(AppState::MODE_DEVELOPER);
        $this->flow->expects(self::once())->method('complete')
            ->with(new Profile('instagram', '555', null, false, 'mario.shop', ''), '')
            ->willReturn('https://shop.test/sociallogin/account/completeemail/');
        $this->redirect->expects(self::once())->method('setUrl')
            ->with('https://shop.test/sociallogin/account/completeemail/');

        self::assertSame($this->redirect, $this->controller->execute());
    }
}
