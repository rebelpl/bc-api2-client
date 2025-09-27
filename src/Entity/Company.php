<?php
namespace Rebel\BCApi2\Entity;

use Rebel\BCApi2\Entity;

class Company extends Entity
{
    public string $id {
        get => $this->get('id');
    }

    public string $name {
        get => $this->get('name');
    }

    public string $displayName {
        get => $this->get('displayName');
    }

    public string $systemVersion {
        get => $this->get('systemVersion');
    }

    public \DateTime $systemCreatedAt {
        get => $this->getAsDateTime('systemCreatedAt');
    }

    public string $systemCreatedBy {
        get => $this->get('systemCreatedBy');
    }

    public \DateTime $systemModifiedAt {
        get => $this->getAsDateTime('systemModifiedAt');
    }

    public string $systemModifiedBy {
        get => $this->get('systemModifiedBy');
    }
}