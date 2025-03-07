<?php

declare(strict_types=1);

/*
 * This file is part of the Sonata Project package.
 *
 * (c) Thomas Rabaix <thomas.rabaix@sonata-project.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonata\MediaBundle\Security;

use Sonata\MediaBundle\Model\MediaInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ForbiddenDownloadStrategy implements DownloadStrategyInterface
{
    private TranslatorInterface $translator;

    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    public function isGranted(MediaInterface $media, Request $request): bool
    {
        return false;
    }

    public function getDescription(): string
    {
        return $this->translator->trans('description.forbidden_download_strategy', [], 'SonataMediaBundle');
    }
}
