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

namespace Sonata\MediaBundle\Provider;

use Gaufrette\Filesystem;
use Imagine\Image\ImagineInterface;
use Sonata\MediaBundle\CDN\CDNInterface;
use Sonata\MediaBundle\Generator\GeneratorInterface;
use Sonata\MediaBundle\Metadata\MetadataBuilderInterface;
use Sonata\MediaBundle\Model\MediaInterface;
use Sonata\MediaBundle\Thumbnail\ThumbnailInterface;
use Symfony\Component\HttpFoundation\File\Exception\UploadException;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class ImageProvider extends FileProvider implements ImageProviderInterface
{
    private ImagineInterface $imagineAdapter;

    public function __construct(string $name, Filesystem $filesystem, CDNInterface $cdn, GeneratorInterface $pathGenerator, ThumbnailInterface $thumbnail, array $allowedExtensions, array $allowedMimeTypes, ImagineInterface $adapter, ?MetadataBuilderInterface $metadata = null)
    {
        parent::__construct($name, $filesystem, $cdn, $pathGenerator, $thumbnail, $allowedExtensions, $allowedMimeTypes, $metadata);

        $this->imagineAdapter = $adapter;
    }

    public function getProviderMetadata(): MetadataInterface
    {
        return new Metadata(
            $this->getName(),
            $this->getName().'.description',
            null,
            'SonataMediaBundle',
            ['class' => 'fa fa-picture-o']
        );
    }

    public function getHelperProperties(MediaInterface $media, string $format, array $options = []): array
    {
        if (isset($options['srcset'], $options['picture'])) {
            throw new \LogicException("The 'srcset' and 'picture' options must not be used simultaneously.");
        }

        if (MediaProviderInterface::FORMAT_REFERENCE === $format) {
            $box = $media->getBox();
        } else {
            $resizerFormat = $this->getFormat($format);
            if (false === $resizerFormat) {
                throw new \RuntimeException(sprintf('The image format "%s" is not defined.
                        Is the format registered in your ``sonata_media`` configuration?', $format));
            }

            if (null === $this->resizer) {
                throw new \RuntimeException('Resizer not set on the image provider.');
            }

            $box = $this->resizer->getBox($media, $resizerFormat);
        }

        $mediaWidth = $box->getWidth();

        $params = [
            'alt' => $media->getDescription() ?? $media->getName(),
            'title' => $media->getName(),
            'src' => $this->generatePublicUrl($media, $format),
            'width' => $mediaWidth,
            'height' => $box->getHeight(),
        ];

        if (isset($options['picture'])) {
            $pictureParams = [];
            foreach ($options['picture'] as $key => $pictureFormat) {
                $formatName = $this->getFormatName($media, $pictureFormat);
                $settings = $this->getFormat($formatName);

                if (false === $settings) {
                    throw new \RuntimeException(sprintf('The image format "%s" is not defined.
                            Is the format registered in your ``sonata_media`` configuration?', $formatName));
                }

                if (null === $this->resizer) {
                    throw new \RuntimeException('Resizer not set on the image provider.');
                }

                $src = $this->generatePublicUrl($media, $formatName);
                $mediaQuery = \is_string($key)
                    ? $key
                    : sprintf('(max-width: %dpx)', $this->resizer->getBox($media, $settings)->getWidth());

                $pictureParams['source'][] = ['media' => $mediaQuery, 'srcset' => $src];
            }

            unset($options['picture']);
            $pictureParams['img'] = $params + $options;
            $params = ['picture' => $pictureParams];
        } elseif (MediaProviderInterface::FORMAT_ADMIN !== $format) {
            $srcSetFormats = $this->getFormats();

            if (isset($options['srcset']) && \is_array($options['srcset'])) {
                $srcSetFormats = [];
                foreach ($options['srcset'] as $srcSetFormat) {
                    $formatName = $this->getFormatName($media, $srcSetFormat);
                    $settings = $this->getFormat($formatName);

                    if (false === $settings) {
                        throw new \RuntimeException(sprintf('The image format "%s" is not defined.
                                Is the format registered in your ``sonata_media`` configuration?', $formatName));
                    }

                    $srcSetFormats[$formatName] = $settings;
                }
                unset($options['srcset']);

                // Make sure the requested format is also in the srcSetFormats
                if (!isset($srcSetFormats[$format])) {
                    $settings = $this->getFormat($format);

                    if (false === $settings) {
                        throw new \RuntimeException(sprintf('The image format "%s" is not defined.
                                Is the format registered in your ``sonata_media`` configuration?', $format));
                    }

                    $srcSetFormats[$format] = $settings;
                }
            }

            if (!isset($options['srcset'])) {
                $srcSet = [];

                foreach ($srcSetFormats as $providerFormat => $settings) {
                    $context = $media->getContext();

                    // Check if format belongs to the current media's context
                    if (null !== $context && 0 === strpos($providerFormat, $context)) {
                        if (null === $this->resizer) {
                            throw new \RuntimeException('Resizer not set on the image provider.');
                        }

                        $width = $this->resizer->getBox($media, $settings)->getWidth();

                        $srcSet[] = sprintf('%s %dw', $this->generatePublicUrl($media, $providerFormat), $width);
                    }
                }

                // The reference format is not in the formats list
                $srcSet[] = sprintf(
                    '%s %dw',
                    $this->generatePublicUrl($media, MediaProviderInterface::FORMAT_REFERENCE),
                    $media->getBox()->getWidth()
                );

                $params['srcset'] = implode(', ', $srcSet);
            }

            $params['sizes'] = sprintf('(max-width: %1$dpx) 100vw, %1$dpx', $mediaWidth);
        }

        return array_merge($params, $options);
    }

    public function updateMetadata(MediaInterface $media, bool $force = true): void
    {
        try {
            if (!$media->getBinaryContent() instanceof \SplFileInfo) {
                // this is now optimized at all!!!
                $path = tempnam(sys_get_temp_dir(), 'sonata_update_metadata');

                if (false === $path) {
                    throw new \LogicException(sprintf('Unable to update metadata for media %s.', $media->getId() ?? ''));
                }

                $fileObject = new \SplFileObject($path, 'w');
                $fileObject->fwrite($this->getReferenceFile($media)->getContent());
            } else {
                $fileObject = $media->getBinaryContent();
            }

            $image = $this->imagineAdapter->open($fileObject->getPathname());
            $size = $image->getSize();

            $media->setSize($fileObject->getSize());
            $media->setWidth($size->getWidth());
            $media->setHeight($size->getHeight());
        } catch (\LogicException $e) {
            $media->setProviderStatus(MediaInterface::STATUS_ERROR);

            $media->setSize(0);
            $media->setWidth(0);
            $media->setHeight(0);
        }
    }

    public function generatePublicUrl(MediaInterface $media, string $format): string
    {
        if (MediaProviderInterface::FORMAT_REFERENCE === $format) {
            $path = $this->getReferenceImage($media);
        } else {
            $path = $this->thumbnail->generatePublicUrl($this, $media, $format);
        }

        // if $path is already an url, no further action is required
        if (null !== parse_url($path, \PHP_URL_SCHEME)) {
            return $path;
        }

        return $this->getCdn()->getPath($path, $media->getCdnIsFlushable());
    }

    public function generatePrivateUrl(MediaInterface $media, string $format): string
    {
        return $this->thumbnail->generatePrivateUrl($this, $media, $format);
    }

    public function getFormatsForContext(string $context): array
    {
        return array_filter($this->getFormats(), static function (string $providerFormat) use ($context): bool {
            return 0 === strpos($providerFormat, $context);
        }, \ARRAY_FILTER_USE_KEY);
    }

    protected function doTransform(MediaInterface $media): void
    {
        parent::doTransform($media);

        if ([] === $this->allowedExtensions) {
            $media->setProviderStatus(MediaInterface::STATUS_ERROR);

            throw new UploadException('There are no allowed extensions for this image.');
        }

        if ([] === $this->allowedMimeTypes) {
            $media->setProviderStatus(MediaInterface::STATUS_ERROR);

            throw new UploadException('There are no allowed mime types for this image.');
        }

        $binaryContent = $media->getBinaryContent();

        if ($binaryContent instanceof UploadedFile) {
            $extension = $binaryContent->getClientOriginalExtension();
        } elseif ($binaryContent instanceof File) {
            $extension = $binaryContent->getExtension();
        } else {
            // Should not happen, FileProvider should throw an exception in that case
            return;
        }

        $extension = '' !== $extension ? $extension : $binaryContent->guessExtension();

        if (!\in_array($extension, $this->allowedExtensions, true)) {
            $media->setProviderStatus(MediaInterface::STATUS_ERROR);

            throw new UploadException(sprintf(
                'The image extension "%s" is not one of the allowed (%s).',
                $extension ?? '',
                '"'.implode('", "', $this->allowedExtensions).'"'
            ));
        }

        $mimeType = $binaryContent->getMimeType();

        if (!\in_array($mimeType, $this->allowedMimeTypes, true)) {
            $media->setProviderStatus(MediaInterface::STATUS_ERROR);

            throw new UploadException(sprintf(
                'The image mime type "%s" is not one of the allowed (%s).',
                $mimeType ?? '',
                '"'.implode('", "', $this->allowedMimeTypes).'"'
            ));
        }

        try {
            $image = $this->imagineAdapter->open($binaryContent->getPathname());
        } catch (\RuntimeException $e) {
            $media->setProviderStatus(MediaInterface::STATUS_ERROR);

            return;
        }

        $size = $image->getSize();

        $media->setWidth($size->getWidth());
        $media->setHeight($size->getHeight());

        $media->setProviderStatus(MediaInterface::STATUS_OK);
    }
}
