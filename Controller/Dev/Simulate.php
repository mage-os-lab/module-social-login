<?php
declare(strict_types=1);

namespace Digitalway\SocialLogin\Controller\Dev;

use Digitalway\SocialLogin\Model\Profile;
use Digitalway\SocialLogin\Model\ProfileLoginFlow;
use Digitalway\SocialLogin\Model\ReturnUrlValidator;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\State as AppState;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;

/**
 * deploy:mode=developer ONLY (404 otherwise): simulates the return from a provider
 * with the profile given in the query string and runs the real flow
 * (ProfileLoginFlow). Used by local functional tests without real OAuth apps.
 */
class Simulate implements HttpGetActionInterface
{
    public function __construct(
        private readonly AppState $appState,
        private readonly RequestInterface $request,
        private readonly ProfileLoginFlow $flow,
        private readonly ReturnUrlValidator $returnUrlValidator,
        private readonly RedirectFactory $redirectFactory,
        private readonly ForwardFactory $forwardFactory,
        private readonly ManagerInterface $messageManager
    ) {
    }

    public function execute(): ResultInterface
    {
        if ($this->appState->getMode() !== AppState::MODE_DEVELOPER) {
            return $this->forwardFactory->create()->forward('noroute');
        }

        $email = $this->param('email');
        $profile = new Profile(
            $this->param('provider') ?: 'instagram',
            $this->param('id') ?: 'sim-' . bin2hex(random_bytes(4)),
            $email === '' ? null : $email,
            $this->param('verified') === '1',
            $this->param('first'),
            $this->param('last')
        );

        $redirect = $this->redirectFactory->create();
        try {
            return $redirect->setUrl(
                $this->flow->complete($profile, $this->returnUrlValidator->sanitize($this->param('return')))
            );
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());

            return $redirect->setPath('customer/account/login');
        }
    }

    private function param(string $key): string
    {
        return trim((string) $this->request->getParam($key));
    }
}
