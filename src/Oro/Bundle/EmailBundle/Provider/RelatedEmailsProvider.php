<?php

namespace Oro\Bundle\EmailBundle\Provider;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\Mapping\ClassMetadata;

use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;

use Oro\Bundle\EmailBundle\Entity\EmailInterface;
use Oro\Bundle\EmailBundle\Model\EmailAttribute;
use Oro\Bundle\EntityConfigBundle\Provider\ConfigProvider;
use Oro\Bundle\SecurityBundle\SecurityFacade;
use Oro\Bundle\LocaleBundle\Formatter\NameFormatter;
use Oro\Bundle\EmailBundle\Tools\EmailAddressHelper;

class RelatedEmailsProvider
{
    /** @var Registry */
    protected $registry;

    /** @var ConfigProvider */
    protected $entityConfigProvider;

    /** @var SecurityFacade */
    protected $securityFacade;

    /** @var NameFormatter */
    protected $nameFormatter;

    /** @var EmailAddressHelper */
    protected $emailAddressHelper;

    /** @var PropertyAccessor*/
    protected $propertyAccessor;

    /**
     * @param Registry $registry
     * @param ConfigProvider $entityConfigProvider
     * @param SecurityFacade $securityFacade
     * @param NameFormatter $nameFormatter
     * @param EmailAddressHelper $emailAddressHelper
     */
    public function __construct(
        Registry $registry,
        ConfigProvider $entityConfigProvider,
        SecurityFacade $securityFacade,
        NameFormatter $nameFormatter,
        EmailAddressHelper $emailAddressHelper
    ) {
        $this->registry = $registry;
        $this->entityConfigProvider = $entityConfigProvider;
        $this->securityFacade = $securityFacade;
        $this->nameFormatter = $nameFormatter;
        $this->emailAddressHelper = $emailAddressHelper;
    }

    /**
     * @param object $object
     * @param int $depth
     * @param bool $ignoreAcl
     *
     * @return array
     */
    public function getEmails($object, $depth = 1, $ignoreAcl = false)
    {
        $emails = [];

        if (!$depth || !($ignoreAcl || !$this->securityFacade->isGranted('VIEW', $object))) {
            if ($this->securityFacade->getLoggedUser() === $object) {
                $ignoreAcl = true;
            } else {
                return $emails;
            }
        }

        $className = ClassUtils::getClass($object);

        $attributes = [];
        $metadata = $this->getMetadata($className);

        if (in_array('Oro\Bundle\EmailBundle\Model\EmailHolderInterface', class_implements($className), true)) {
            $attributes[] = new EmailAttribute('email');
        }

        $propertyAccessor = PropertyAccess::createPropertyAccessor();

        $attributes = array_merge($attributes, $this->getFieldAttributes($metadata));

        foreach ($metadata->associationMappings as $name => $assoc) {
            if (in_array(
                'Oro\Bundle\EmailBundle\Entity\EmailInterface',
                class_implements($assoc['targetEntity']),
                true
            )) {
                $attributes[] = new EmailAttribute($name, true);
            } else {
                if ($depth > 1) {
                    $assocObject = $propertyAccessor->getValue($object, $name);
                    if (!$assocObject instanceof \Traversable && !is_array($assocObject)) {
                        if ($assocObject) {
                            $assocObject = [$assocObject];
                        } else {
                            $assocObject = [];
                        }
                    }
                    foreach ($assocObject as $obj) {
                        $emails = array_merge($emails, $this->getEmails($obj, $depth - 1));
                    }
                }
            }
        }

        return array_merge($emails, $this->createEmailsFromAttributes($attributes, $object));
    }

    /**
     * @param EmailAttribute[] $attributes
     * @param object $object
     *
     * @return array
     */
    protected function createEmailsFromAttributes(array $attributes, $object)
    {
        $emails = [];

        foreach ($attributes as $attribute) {
            $value = $this->getPropertyAccessor()->getValue($object, $attribute->getName());
            if (!$value instanceof \Traversable) {
                $value = [$value];
            }

            foreach ($value as $email) {
                if (is_scalar($email)) {
                    $emails[$email] = $this->formatEmail($object, $email);
                } elseif ($email instanceof EmailInterface) {
                    $emails[$email->getEmail()] = $this->formatEmail($email->getEmailOwner(), $email->getEmail());
                }
            }
        }

        return $emails;
    }

    /**
     * @param ClassMetadata $metadata
     *
     * @return EmailAttribute[]
     */
    protected function getFieldAttributes(ClassMetadata $metadata)
    {
        $attributes = [];
        foreach ($metadata->fieldNames as $fieldName) {
            if (false !== stripos($fieldName, 'email')) {
                $attributes[] = new EmailAttribute($fieldName);
                continue;
            }

            if (!$this->entityConfigProvider->hasConfig($metadata->name, $fieldName)) {
                continue;
            }

            $fieldConfig = $this->entityConfigProvider->getConfig($metadata->name, $fieldName);
            if ($fieldConfig->has('contact_information') && $fieldConfig->get('contact_information') === 'email') {
                $attributes[] = new EmailAttribute($fieldName);
            }
        }

        return $attributes;
    }

    /**
     * @param object|null $owner
     * @param string $email
     *
     * @return string
     */
    protected function formatEmail($owner, $email)
    {
        if (!$owner) {
            return $email;
        }

        $ownerName = $this->nameFormatter->format($owner);

        return $this->emailAddressHelper->buildFullEmailAddress($email, $ownerName);
    }

    /**
     * @return PropertyAccessor
     */
    protected function getPropertyAccessor()
    {
        if (!$this->propertyAccessor) {
            $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
        }

        return $this->propertyAccessor;
    }

    /**
     * @param string $className
     *
     * @return ClassMetadata
     */
    protected function getMetadata($className)
    {
        $em = $this->registry->getManagerForClass($className);

        return $em->getClassMetadata($className);
    }
}
