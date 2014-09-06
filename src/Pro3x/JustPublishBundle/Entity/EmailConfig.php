<?php

namespace Pro3x\JustPublishBundle\Entity;

class EmailConfig {
    
    private $secretKey;
    private $editUrl;
    private $showUrl;
    
    private $activateRoute;
    private $activationParams;
    
    private $email;
    private $location;

    public function getSecretKey() {
        return $this->secretKey;
    }

    public function getEditUrl() {
        return $this->editUrl;
    }
    public function getActivateRoute() {
        return $this->activateRoute;
    }

    public function getActivationParams() {
        return $this->activationParams;
    }

    public function setActivateRoute($activateRoute) {
        $this->activateRoute = $activateRoute;
        return $this;
    }

    public function setActivationParams($activationParams) {
        $this->activationParams = $activationParams;
        return $this;
    }

    public function getShowUrl() {
        return $this->showUrl;
    }

    public function setSecretKey($secretKey) {
        $this->secretKey = $secretKey;
        return $this;
    }

    public function setEditUrl($editUrl) {
        $this->editUrl = $editUrl;
        return $this;
    }

    public function setShowUrl($showUrl) {
        $this->showUrl = $showUrl;
        return $this;
    }
    
    public function getEmail() {
        return $this->email;
    }

    public function setEmail($email) {
        $this->email = $email;
        return $this;
    }
    
    public function getLocation() {
        return $this->location;
    }

    public function setLocation($location) {
        $this->location = $location;
        return $this;
    }

}
