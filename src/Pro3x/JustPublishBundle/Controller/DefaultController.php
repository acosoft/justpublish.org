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
            $location = preg_replace(array('#[^a-z0-9\s\-\.]#', '#\s+\-*#'), array('', '-'), $location);

            $content = $this->getDoctrine()->getRepository('Pro3xJustPublishBundle:Content')->find($location);

            $params = array();

            $params['location'] = $location;

            if($content)
            {
                $code = $request->cookies->get(md5($location));
                
                //compatibility layer, check cookie by location
                if($code == null)
                {
                    $code = $request->cookies->get($location);
                }
                
                if($code == $content->getSecret())
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
     * @Route("/activate/{location}/{code}", name="activate")
     */
    public function activateAction($location, $code)
    {
        $manager = $this->getDoctrine()->getManager();
        $content = $manager->getRepository('Pro3xJustPublishBundle:Content')->find($location); /* @var $content Content */
        
        if($content && $content->getCode() == $code)
        {
            if(!$content->getActivated())
            {
                $content->setActivated(new \DateTime());
                $manager->persist($content);
                $manager->flush();

                return $this->render('Pro3xJustPublishBundle:Default:activate.html.twig', array(
                    'location' => $location,
                    'show' => $this->generateUrl('show', array('location' => $location))
                ));
            }
            else
            {
                return $this->render('Pro3xJustPublishBundle:Default:activation-repeated.html.twig', array(
                    'location' => $location,
                    'show' => $this->generateUrl('show', array('location' => $location))
                ));
            }
        }
        else
        {
            return $this->render('Pro3xJustPublishBundle:Default:invalid-activation-code.html.twig', array('location' => $location));
        }
    }
    
    /**
     * @param Content $content
     */
    private function sendEmail($content)
    {
        $body = $this->renderView('Pro3xJustPublishBundle:Default:email.html.twig', array(
            'location' => $this->generateUrl('show', array('location' => $content->getLocation()), true),
            'activation' => $this->generateUrl('activate', array('code' => $content->getCode(), 'location' => $content->getLocation()), true),
            'secret' => $content->getSecret(),
            'edit' => $this->generateUrl('edit', array('location' => $content->getLocation()), true)
        ));
        
        $message = \Swift_Message::newInstance()
                ->setSender('acosoft@gmail.com')
                ->setFrom('acosoft@gmail.com', 'JustPublish.org')
                ->setSubject("JustPublish.org: " . $content->getLocation())
                ->setTo($content->getEmail())
                ->setBody($body, 'text/html');
        
        $this->get('mailer')->send($message);
    }
    
    /**
     * @Route("/unlock/{location}", name="unlock")
     * @Template()
     */
    function unlockAction($location)
    {
        return array('location' => $location);
    }
    
    /**
     * @Route("/check/{location}", name="check")
     */
    public function checkLockAction(Request $request, $location)
    {
        $content = $this->getDoctrine()->getRepository('Pro3xJustPublishBundle:Content')->find($location); /* @var $content Content */
        $code = $request->get('code');
        
        $result = array();
        
        $result['valid'] = $content && $code && $content->getSecret() == $code;
        $result['location'] = $this->generateUrl('edit', array('location' => $location));
        $result['code'] = $code;
        
        $response = new Response(json_encode($result));
        $response->headers->setCookie(new Cookie(md5($location), $code));
        
        return $response;
    }


    /**
     * @Route("/{location}/edit", name="edit")
     * @Method({"GET"})
     * @Template()
     */
    public function editAction(Request $request, $location)
    {
        $content = $this->getDoctrine()->getRepository('Pro3xJustPublishBundle:Content')->find($location);
        
        $params = array('location' => $location, 'showEmail' => true, 'email' => $request->cookies->get('email'));
        
        if($content)
        {
            $code = $request->cookies->get(md5($location));
            
            //compatibility layer, check cookie by location
            if($code == null)
            {
                $code = $request->cookies->get($location);
            }
            
            if($code == $content->getSecret())
            {
                $params['body'] = $content->getBody();
                $params['showEmail'] = false;
            }
            else
            {
                return $this->redirect($this->generateUrl('unlock', array('location' => $location)));
            }
        }
        else
        {
            $params['body'] = $this->renderView("Pro3xJustPublishBundle:Default:welcome.html.twig");
        }
        
        return $params;
    }
    
    private function isHome($host)
    {
        if(in_array($host, array('justpublish.org', 'localhost')))
        {
            return true;
        }
        else
        {
            return false;
        }
    }
    
    /**
     * @Route("/", name="home")
     * @Route("/edit")
     * @Template()
     */
    public function indexAction(Request $request)
    {
        $host = $request->getHttpHost();
        
        if($this->isHome($host))
        {
            return array('body' => $this->renderView("Pro3xJustPublishBundle:Default:welcome.html.twig"),
                'location' => 'home');
        }
        else
        {
            return $this->showAction($host);
        }
    }
    
    /**
     * @Route("/{location}", name="show")
     */
    public function showAction($location)
    {
        $content = $this->getDoctrine()->getRepository('Pro3xJustPublishBundle:Content')->find($location);
        /* @var $content Content */
        
        if($content)
        {
            return new Response($content->getBody());
        }
        else
        {
            return $this->render('Pro3xJustPublishBundle:Default:start.html.twig', array('location' => $location));
        }
    }
    
    /**
     * @Route("/{location}/save", name="save")
     * @Method({"POST"})
     */
    public function saveAction(Request $request, $location)
    {
        if($location != 'home')
        {
            $updateEmail = false;
            
            $manager = $this->getDoctrine()->getManager();

            $content = $manager->getRepository("Pro3xJustPublishBundle:Content")->find($location);

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

            $content->setLocation($location);
            $content->setBody($request->get('body'));
            $content->setPublished(new \DateTime());

            $manager->persist($content);
            $manager->flush();
            
            $view = $this->renderView('Pro3xJustPublishBundle:Default:save-changes.html.twig', array('location' => $location));
            
            $response = new Response(json_encode(array('valid' => true, 'replace' => $view)));
            $response->headers->setCookie(new Cookie(md5($location), $content->getSecret(), time() + (3600 * 24 * 7)));
            
            if($updateEmail)
            {
                $response->headers->setCookie(new Cookie('email', $content->getEmail(), time() + (3600 * 24 * 365)));
                $this->sendEmail($content);
            }
            
            return $response;
        }
        
        return new Response(json_encode(array('valid' => true)));
    }
}
