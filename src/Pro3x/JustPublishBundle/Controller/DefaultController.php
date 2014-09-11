<?php

namespace Pro3x\JustPublishBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Pro3x\JustPublishBundle\Entity\Content;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Cookie;

class DefaultController extends Controller
{
    /**
     * @Route("/create", name="create")
     * @Route("/create/edit")
     */
    public function createAction(Request $request)
    {
        $location = $request->get("query", '');
        
        if($location)
        {
            $location = preg_replace(array('#[^a-z0-9\s\-\./]#', '#\s+\-*#'), array('', '-'), $location);

            $content = $this->getDoctrine()->getRepository('Pro3xJustPublishBundle:Content')->find($location);

            $params = array();

            $params['location'] = $location;

            if($content)
            {
                $code = $this->findSecretCode($request, $location);
                
                if($content->isValidSecret($code))
                {
                    $params['available'] = true;
                    $params['edit'] = $this->generateUrl('edit', array('location' => $location));
                }
                else 
                {
                    $params['available'] = false;
                    $params['edit'] = $this->generateUrl('unlock', array('location' => $location));
                }
            }
            else 
            { 
                $params['available'] = true;
                $params['edit'] = $this->generateUrl('edit', array('location' => $location));
            }
        }
        else
        {
            $params['location'] = $this->generateUrl('home');
            $params['edit'] = $this->generateUrl('home');
            $params['available'] = true;
        }
        
        return new Response(json_encode($params));
    }
    
    /**
     * @Route("/email/{email}", name="email")
     * @Template()
     */
    public function emailAction($email)
    {
        $content = $this->getDoctrine()->getRepository('Pro3xJustPublishBundle:Content')->find('aco');
        $this->sendEmail($content);
        
        return array(
            'location' => $this->generateUrl('show', array('location' => 'aco'), true),
            'activation' => $this->generateUrl('activate', array('code' => uniqid()), true),
            'secret' => $content->getSecret()
        );
    }
    
    /**
     * @Route("/activate/{location}/{code}", host="%domain%", name="activate", requirements={"location"=".+"})
     */
    public function activateAction(Request $request, $location, $code)
    {
        $host = $request->getHttpHost();
        $showUrl = $this->generateUrl('show', array('location' => $location));
        return $this->activateLocation($request, $request->getHttpHost(), $location, $location, $code, $showUrl);
    }
    
    /**
     * @Route("/activate/{location}/{code}", host="{host}", name="hostActivate", requirements={"location"=".+", "host"=".++"})
     */
    public function hostActivateAction(Request $request, $host, $location, $code)
    {
        $showUrl = $this->generateUrl('hostShow', array('location' => $location, 'host' => $host));
        return $this->activateLocation($request, $host, $location, $this->getLocation($host, $location), $code, $showUrl);
    }
    
    public function activateLocation(Request $request, $host, $location, $key, $code, $showUrl)
    {
        $manager = $this->getDoctrine()->getManager();
        $content = $manager->getRepository('Pro3xJustPublishBundle:Content')->find($key); /* @var $content Content */
        
        if($content && $content->getCode() == $code)
        {
            if(!$content->getActivated())
            {
                $content->setActivated(new \DateTime());
                $manager->persist($content);
                $manager->flush();

                return $this->render('Pro3xJustPublishBundle:Default:activate.html.twig', array(
                    'location' => $location,
                    'show' => $showUrl
                ));
            }
            else
            {
                return $this->render('Pro3xJustPublishBundle:Default:activation-repeated.html.twig', array(
                    'location' => $location,
                    'show' => $showUrl
                ));
            }
        }
        else
        {
            return $this->render('Pro3xJustPublishBundle:Default:invalid-activation-code.html.twig', array('location' => $location, 'show' => $showUrl));
        }
    }

    /**
     * 
     * @param \Pro3x\JustPublishBundle\Entity\EmailConfig $config
     */
    private function sendEmail($config)
    {
        $body = $this->renderView('Pro3xJustPublishBundle:Default:email.html.twig', array(
            'location' => $config->getShowUrl(),
            'activation' => $this->generateUrl($config->getActivateRoute(), $config->getActivationParams(), true),
            'secret' => $config->getSecretKey(),
            'edit' => $config->getEditUrl()
        ));
        
        $message = \Swift_Message::newInstance()
                ->setSender('acosoft@gmail.com')
                ->setFrom('acosoft@gmail.com', 'JustPublish.org')
                ->setSubject("JustPublish.org: " . $config->getLocation())
                ->setTo($config->getEmail())
                ->setBody($body, 'text/html');
        
        $this->get('mailer')->send($message);
    }
    
    /**
     * @Route("/unlock/{location}", name="unlock", host="%domain%", requirements={"location"=".+"})
     * @Template("Pro3xJustPublishBundle:Default:unlock.html.twig")
     */
    function unlockAction($location)
    {
        return array('location' => $location);
    }
    
    /**
     * @Route("/unlock/{location}", name="hostUnlock", host="{host}", requirements={"location"=".+", "host"=".++"})
     * @Template("Pro3xJustPublishBundle:Default:unlock.html.twig")
     */
    function hostUnlockAction($location)
    {
        return array('location' => $location);
    }
    
    /**
     * @Route("/check/{location}", host="%domain%", requirements={"location"=".+"})
     */
    public function checkLockAction(Request $request, $location)
    {
        $editUrl = $this->generateUrl('edit', array('location' => $location));
        return $this->verifySecret($request, $location, $request->getHttpHost(), $this->generateUrl('edit', array('location' => $location)));
    }
    
    /**
     * @Route("/check/{location}", name="check", host="{host}", requirements={"location"=".+", "host"=".++"})
     */
    public function hostCheckLockAction(Request $request, $location, $host)
    {
        $editUrl = $this->getEditUrl($host, $location);
        return $this->verifySecret($request, $this->getLocation($host, $location), $host, $editUrl);
    }

    public function verifySecret(Request $request, $location, $host, $editUrl)
    {
        $content = $this->getDoctrine()->getRepository('Pro3xJustPublishBundle:Content')->find($location); /* @var $content Content */
        $code = $request->get('code');
        
        $result = array();
        
        $result['valid'] = $content && $code && $content->getSecret() == $code;
        $result['location'] = $editUrl;
        $result['code'] = $code;
        
        $response = new Response(json_encode($result));
        $response->headers->setCookie(new Cookie(md5($location), $code));
        
        return $response;
    }
    
    /**
     * @Route("/{domain}/{page}/edit", host="%domain%", requirements={"page"=".+"})
     * @Method({"GET"})
     */
    public function editPageAction(Request $request, $domain, $page)
    {
        $location = $domain . '/' . $page;
        $content = $this->findLocation($domain);
        
        if($content && $content->isValidSecret($this->findSecretCode($request, $domain)) == false)
        {
            return $this->renderUnlock($this->generateUrl('unlock', array('location' => $domain, 'back' => $request->getUri())));
        }
        else
        {
            return $this->editAction($request, $location);
        }
    }

    /**
     * @Route("/{location}/edit", name="edit", host="%domain%", requirements={"location"=".+"})
     * @Method({"GET"})
     */
    public function editAction(Request $request, $location)
    {
        $showUrl = $this->generateUrl('show', array('location' => $location));
        $unlockUrl = $this->generateUrl('unlock', array('location' => $location));
        $saveUrl = $this->generateUrl('save', array('location' => $location));
        
        return $this->editLocation($request, $location, $showUrl, $unlockUrl, $saveUrl);
    }
        
    /**
     * @Route("/{location}/edit", host="{host}", name="hostEdit", requirements={"location"=".*", "host"=".++"})
     * @Method({"GET"})
     */
    public function hostEditAction(Request $request, $host, $location)
    {
        $content = $this->findLocation($host);
        
        if($content && $content->isValidSecret($this->findSecretCode($request, $host)) == false)
        {
            return $this->renderUnlock($this->generateUrl('hostUnlock', array('location' => $host, 'host' => $host, 'back' => $request->getUri())));
        }
        else
        {
            $showUrl = $this->getShowUrl($host, $location);
            $hostLocation = $this->getLocation($host, $location);
            $unlockUrl = $this->generateUrl('hostUnlock', array('location' => $location, 'host' => $host));
            $saveUrl = $this->generateUrl('hostSave', array('location' => $location, 'host' => $host));

            return $this->editLocation($request, $hostLocation, $showUrl, $unlockUrl, $saveUrl);
        }
    }
    
    public function editLocation(Request $request, $location, $showUrl, $unlockUrl, $saveUrl)
    {
        $content = $this->findLocation($location);
        
        $params = array('location' => $location, 'showUrl' => $showUrl, 'saveUrl' => $saveUrl, 'showEmail' => true, 'email' => $request->cookies->get('email'));
        
        if($content)
        {
            $code = $this->findSecretCode($request, $location);
            
            if($content->isValidSecret($code))
            {
                $params['body'] = $content->getBody();
                $params['showEmail'] = false;
            }
            else
            {
                return $this->renderUnlock($unlockUrl);
            }
        }
        else
        {
            $params['body'] = $this->renderView("Pro3xJustPublishBundle:Default:welcome.html.twig");
        }
        
        return $this->render("Pro3xJustPublishBundle:Default:edit.html.twig", $params);
    }
    
    private function findSecretCode($request, $location)
    {
        $code = $request->cookies->get(md5($location));
            
        //compatibility layer, check cookie by location
        if($code == null)
        {
            $code = $request->cookies->get($location);
        }
        
        return $code;
    }
    
    private function renderUnlock($location)
    {
        return $this->redirect($location);
    }
    
    /**
     * 
     * @param type $location
     * @return Content
     */
    private function findLocation($location)
    {
        return $this->getDoctrine()->getRepository('Pro3xJustPublishBundle:Content')->find($location);
    }
    
    /**
     * @Route("/edit", host="%domain%")
     */
    public function indexEditAction()
    {
        return $this->redirect($this->generateUrl('home'));
    }
    
    /**
     * @Route("/edit", name="hostIndexEdit", host="{host}", requirements={"host"=".++"})
     */
    public function hostIndexEditAction(Request $request)
    {
        $host = $request->getHost();
        return $this->hostEditAction($request, $host, $host);
    }
    
    private function renderHome()
    {
        return $this->render("Pro3xJustPublishBundle:Default:index.html.twig");
    }
    
    /**
     * @Route("/", name="home", host="%domain%")
     */
    public function indexAction()
    {        
        return $this->renderHome();
    }
    
    /**
     * @Route("/")
     */
    public function hostIndexAction(Request $request)
    {
        $host = $request->getHttpHost();
        return $this->showLocation($host, $host, $this->getEditUrl($host, $host));
    }
    
    /**
     * 
     * @param type $request
     * @param type $key
     * @param \Pro3x\JustPublishBundle\Entity\EmailConfig $config
     * @return \Symfony\Component\HttpFoundation\Response
     */
    private function saveLocation($request, $key, $config)
    {
        if($key != 'home')
        {
            $updateEmail = false;
            
            $manager = $this->getDoctrine()->getManager();

            $content = $manager->getRepository("Pro3xJustPublishBundle:Content")->find($key);

            if(!$content)
            {
                $content = new Content;
                $content->setSecret(uniqid());
                $content->setCode(uniqid());
                
                $email = $request->get('email');
                
                if(!$email)
                {
                    return new Response(json_encode(array(
                        'valid' => false, 
                        'title' => 'Invalid email address', 
                        'message' => 'Please enter valid email address. '
                        . 'We will send an activation link to your email with '
                        . 'secret key you can use to edit published content in the future.')));
                }
                else
                {
                    
                    $content->setEmail($email);
                    $updateEmail = true;
                }
            }       

            $content->setLocation($key);
            $content->setBody($request->get('body'));
            $content->setPublished(new \DateTime());

            $manager->persist($content);
            $manager->flush();
            
            $view = $this->renderView('Pro3xJustPublishBundle:Default:save-changes.html.twig', array('showUrl' => $config->getShowUrl()));
            
            $response = new Response(json_encode(array('valid' => true, 'replace' => $view)));
            $response->headers->setCookie(new Cookie(md5($key), $content->getSecret(), time() + (3600 * 24 * 7)));
            
            if($updateEmail)
            {
                $response->headers->setCookie(new Cookie('email', $content->getEmail(), time() + (3600 * 24 * 365)));
                
                $params = $config->getActivationParams();
                $params['code'] = $content->getCode();
                $config->setActivationParams($params);
                
                $config->setEmail($content->getEmail());
                
                $config->setSecretKey($content->getSecret());
                $this->sendEmail($config);
            }
            
            return $response;
        }
        
        return new Response(json_encode(array('valid' => true)));
    }
    
    /**
     * @Route("/{location}/save", host="%domain%", requirements={"location"=".+", "host"=".++"})
     * @Method({"POST"})
     */
    public function saveAction(Request $request, $location)
    {
        $mc = new \Pro3x\JustPublishBundle\Entity\EmailConfig();
        $host = $request->getHttpHost();
        
        $params = array('location' => $location);
        
        $mc->setEditUrl($this->generateUrl('edit', $params, true));
        $mc->setShowUrl($this->generateUrl('show', $params, true));
        
        $mc->setActivationParams($params);
        $mc->setActivateRoute('activate');
        
        $mc->setLocation($location);
        
        return $this->saveLocation($request, $location, $mc);
    }
    
    private function getShowUrl($host, $location)
    {
        if($host == $location)
        {
            $showLocation = "";
        }
        else
        {
            $showLocation = $location;
        }
        
        return $this->generateUrl('hostShow', array('location' => $showLocation, 'host' => $host), true);
    }
    
    /**
     * @Route("/{location}/save", name="hostSave", host="{host}", requirements={"location"=".+", "host"=".++"})
     * @Route("/{location}/save", name="save", requirements={"location"=".+"})
     * @Method({"POST"})
     */
    public function hostSaveAction(Request $request, $host, $location)
    {
        $mc = new \Pro3x\JustPublishBundle\Entity\EmailConfig();
        
        $params = array('host' => $host, 'location' => $location);
        
        $mc->setEditUrl($this->generateUrl('hostEdit', $params, true));
        $mc->setShowUrl($this->getShowUrl($host, $location));
        
        $mc->setActivationParams($params);
        $mc->setActivateRoute('hostActivate');
        
        $mc->setLocation($location);
        
        return $this->saveLocation($request, $this->getLocation($host, $location), $mc);
    }
    
    private function getEditUrl($host, $location)
    {
        if($host == $location)
        {
            return $this->generateUrl('hostIndexEdit', array('host' => $host));
        }
        else
        {
            return $this->generateUrl('hostEdit', array('location' => $location, 'host' => $host));
        }
    }
    
    /**
     * @Route("/{location}", host="{host}", requirements={"location"=".*\.mailto", "host"=".++"})
     */
    public function mailto(Request $request, $host, $location)
    {
        $email = $request->get('email', null);
        
        $a = $request->get('a');
        $b = $request->get('b');
        
        $content = $this->findLocation($this->getLocation($host, $location));
        
        if(!$email) {
            return new \Symfony\Component\HttpFoundation\JsonResponse(array('status' => 'error', 'description' => 'Missing parameter: email'));
        } else if(!$content) { 
            return new \Symfony\Component\HttpFoundation\JsonResponse(array('status' => 'error', 'description' => "Unknown template: $location"));
        } else if ($a - $b != 1002) {
            return new \Symfony\Component\HttpFoundation\JsonResponse(array('status' => 'error', 'description' => 'Invalid script result'));
        } else {
            $params = $request->get('xp', array());
            
            $keys = array();
            $values = array();
            
            foreach ($params as $key => $value) {
                $keys[] = '{{' . $key . '}}';
                $values[] = $value;
            }
            
            $body = str_replace($keys, $values, $content->getBody());
            
            $message = \Swift_Message::newInstance("JustPublish.org: Message from $location", $body, 'text/html', 'utf8')
                    ->setSender('acosoft@gmail.com')
                    ->setTo($content->getEmail())
                    ->setReplyTo($email);
            
            $this->get('mailer')->send($message);
            
            return new \Symfony\Component\HttpFoundation\JsonResponse(array('status' => 'delivered'));
        }
    }
    
    /**
     * @Route("/{host}/{location}", requirements={"location"=".*"})
     */
    public function hostShowPageAction($location, $host)
    {
        return $this->showLocation($host, 
                $this->getLocation($host, $location),
                $this->getEditUrl($host, $location));
    }
    
    /**
     * @Route("/{location}", name="show", host="%domain%", requirements={"location"=".*"})
     */
    public function showAction(Request $request, $location)
    {
        return $this->showLocation(
                $request->getHttpHost(), 
                $location,
                $this->generateUrl('edit', array('location' => $location)));
    }
    
    /**
     * @Route("/{location}", name="hostShow", host="{host}", requirements={"location"=".*", "host"=".++"})
     */
    public function hostShowAction(Request $request, $host, $location)
    {
        return $this->showLocation(
                $host, 
                $this->getLocation($host, $location),
                $this->getEditUrl($host, $location));
    }
    
    private function showLocation($host, $location, $editUrl)
    {
        $content = $this->getDoctrine()->getRepository('Pro3xJustPublishBundle:Content')->find($location);
        /* @var $content Content */
        
        if($content)
        {
            return new Response($content->getBody());
        }
        else
        {
            return $this->render('Pro3xJustPublishBundle:Default:start.html.twig', array('editUrl' => $editUrl, 'location' => $location, 'host' => $host));
        }
    }
    
    private function getLocation($host, $location)
    {
        if($host == $location)
        {
            return $host;
        }
        else
        {
            return $host . '/' . $location;
        }
    }
    
}
