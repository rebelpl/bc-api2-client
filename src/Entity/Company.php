<?php
namespace Rebel\BCApi2\Entity;

use Rebel\BCApi2\Entity;

class Company extends Entity
{
    protected string $primaryKey = 'id';
    
    public string $id {
        get => $this->get('id', 'guid');
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
        get => $this->get('systemCreatedAt', 'datetime');
    }

    public string $systemCreatedBy {
        get => $this->get('systemCreatedBy', 'guid');
    }

    public \DateTime $systemModifiedAt {
        get => $this->get('systemModifiedAt', 'datetime');
    }

    public string $systemModifiedBy {
        get => $this->get('systemModifiedBy', 'guid');
    }
}