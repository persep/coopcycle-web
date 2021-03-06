<?php

namespace AppBundle\Controller\Utils;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\StripePayment;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Form\OrdersExportType;
use Sylius\Component\Payment\PaymentTransitions;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

trait OrderTrait
{
    abstract protected function getOrderList(Request $request);

    private function orderAsJson(Order $order)
    {
        $orderNormalized = $this->get('api_platform.serializer')->normalize($order, 'jsonld', [
            'resource_class' => Order::class,
            'operation_type' => 'item',
            'item_operation_name' => 'get',
            'groups' => ['order', 'place']
        ]);

        return new JsonResponse($orderNormalized, 200);
    }

    public function orderListAction(Request $request)
    {
        $response = new Response();

        // Allow retrieving deleted entities anyway
        $this->getDoctrine()->getManager()->getFilters()->disable('soft_deleteable');

        $showCanceled = false;
        if ($request->query->has('show_canceled')) {
            $showCanceled = $request->query->getBoolean('show_canceled');
            $response->headers->setCookie(new Cookie('__show_canceled', $showCanceled ? 'on' : 'off'));
        } elseif ($request->cookies->has('__show_canceled')) {
            $showCanceled = $request->cookies->getBoolean('__show_canceled');
        }

        $exportForm = $this->createForm(OrdersExportType::class);

        $authorizationChecker = $this->get('security.authorization_checker');
        if ($authorizationChecker->isGranted('ROLE_ADMIN')) {

            $exportForm->handleRequest($request);
            if ($exportForm->isSubmitted() && $exportForm->isValid()) {
                $data = $exportForm->getData();

                $start = $exportForm->get('start')->getData();
                $end = $exportForm->get('end')->getData();

                $filename = sprintf('orders-%s-%s.csv', $start->format('Y-m-d'), $end->format('Y-m-d'));

                $response = new Response($data['csv']);
                $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
                    ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                    $filename
                ));

                return $response;
            }
        }

        $routes = $request->attributes->get('routes');

        [ $orders, $pages, $page ] = $this->getOrderList($request);

        return $this->render($request->attributes->get('template'), [
            'orders' => $orders,
            'pages' => $pages,
            'page' => $page,
            'routes' => $request->attributes->get('routes'),
            'show_canceled' => $showCanceled,
            'export_form' => $exportForm->createView(),
        ], $response);
    }

    public function orderInvoiceAction($number, Request $request)
    {
        $order = $this->get('sylius.repository.order')->findOneBy([
            'number'=> $number
        ]);

        $this->accessControl($order);

        $html = $this->renderView('@App/order/invoice.html.twig', [
            'order' => $order
        ]);

        return new Response($this->get('knp_snappy.pdf')->getOutputFromHtml($html), 200, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function acceptRestaurantOrderAction($restaurantId, $orderId, Request $request)
    {
        $order = $this->get('sylius.repository.order')->find($orderId);

        $this->accessControl($order->getRestaurant());

        try {
            $this->get('coopcycle.order_manager')->accept($order);
            $this->get('sylius.manager.order')->flush();
        } catch (\Exception $e) {
            // TODO Add flash message
        }

        return $this->redirectToRoute($request->attributes->get('redirect_route'), [
            'restaurantId' => $restaurantId,
            'orderId' => $orderId
        ]);
    }

    public function acceptOrderAction($id, Request $request)
    {
        $order = $this->get('sylius.repository.order')->find($id);

        $this->accessControl($order->getRestaurant());

        try {
            $this->get('coopcycle.order_manager')->accept($order);
            $this->get('sylius.manager.order')->flush();
        } catch (\Exception $e) {
            // TODO Add flash message
        }

        if ($request->isXmlHttpRequest()) {

            return $this->orderAsJson($order);
        }
    }

    public function refuseRestaurantOrder($restaurantId, $orderId, Request $request)
    {
        $order = $this->get('sylius.repository.order')->find($orderId);

        $this->accessControl($order->getRestaurant());

        try {
            $this->get('coopcycle.order_manager')->refuse($order);
            $this->get('sylius.manager.order')->flush();
        } catch (\Exception $e) {
            // TODO Add flash message
        }

        return $this->redirectToRoute($request->attributes->get('redirect_route'), [
            'restaurantId' => $restaurantId,
            'orderId' => $orderId
        ]);
    }

    public function refuseOrderAction($id, Request $request)
    {
        $order = $this->get('sylius.repository.order')->find($id);

        $this->accessControl($order->getRestaurant());

        try {
            $this->get('coopcycle.order_manager')->refuse($order);
            $this->get('sylius.manager.order')->flush();
        } catch (\Exception $e) {
            // TODO Add flash message
        }

        if ($request->isXmlHttpRequest()) {

            return $this->orderAsJson($order);
        }
    }

    public function readyOrderAction($restaurantId, $orderId, Request $request)
    {
        $order = $this->get('sylius.repository.order')->find($orderId);

        $this->accessControl($order->getRestaurant());

        $this->get('coopcycle.order_manager')->ready($order);
        $this->get('sylius.manager.order')->flush();

        return $this->redirectToRoute($request->attributes->get('redirect_route'), [
            'restaurantId' => $restaurantId,
            'orderId' => $orderId
        ]);
    }

    public function delayRestaurantOrderAction($restaurantId, $orderId, Request $request)
    {
        $order = $this->get('sylius.repository.order')->find($orderId);

        $this->accessControl($order->getRestaurant());

        $this->get('coopcycle.order_manager')->delay($order);
        $this->get('sylius.manager.order')->flush();

        return $this->redirectToRoute($request->attributes->get('redirect_route'), [
            'restaurantId' => $restaurantId,
            'orderId' => $orderId
        ]);
    }

    public function delayOrderAction($id, Request $request)
    {
        $order = $this->get('sylius.repository.order')->find($id);

        $this->accessControl($order->getRestaurant());

        try {
            $this->get('coopcycle.order_manager')->delay($order);
            $this->get('sylius.manager.order')->flush();
        } catch (\Exception $e) {
            // TODO Add flash message
        }

        if ($request->isXmlHttpRequest()) {

            return $this->orderAsJson($order);
        }
    }

    private function cancelOrderById($id)
    {
        $order = $this->get('sylius.repository.order')->find($id);
        $this->accessControl($order->getRestaurant());

        $this->get('coopcycle.order_manager')->cancel($order);
        $this->get('sylius.manager.order')->flush();

        return $order;
    }

    public function cancelOrderFromDashboardAction($restaurantId, $orderId, Request $request)
    {
        $this->cancelOrderById($orderId);

        return $this->redirectToRoute($request->attributes->get('redirect_route'), [
            'restaurantId' => $restaurantId,
            'orderId' => $orderId
        ]);
    }

    public function cancelOrderAction($id, Request $request)
    {
        $order = $this->cancelOrderById($id);

        if ($request->isXmlHttpRequest()) {

            return $this->orderAsJson($order);
        }

        return $this->redirectToRoute($request->attributes->get('redirect_route'));
    }
}
