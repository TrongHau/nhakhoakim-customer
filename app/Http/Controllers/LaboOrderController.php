<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Repositories\LaboOrderRepository;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class LaboOrderController extends Controller
{
    public function createCommentOrder(Request $request) {
        $validator = Validator::make($request->all(), [
            'Id'    => 'required|numeric',
            'Content'    => 'required',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'CCOR0004', self::$ERROR);
            return $this->json(false, 'bool');
        }

        try {
            
            $laboOrderRepository = new LaboOrderRepository;
            $result = $laboOrderRepository->createCommentOrder($request->all());

            if ($result) {
                $this->addMessage("Tạo trao đổi đơn hàng thành công.", 'CCOR0001', self::$SUCCESS);
                return $this->json(true, 'bool');
            }
            $this->addMessage("Tạo trao đổi đơn hàng không thành công.", 'CCOR0002', self::$ERROR);
            return $this->json(false, 'bool');

        } catch (\Exception $e) {

            $this->addMessage("Tạo trao đổi đơn hàng không thành công.", 'CCOR0003', self::$ERROR);
            return $this->json(false, 'bool');

        }
    }

    public function getListCommentOrder(Request $request) {
        $validator = Validator::make($request->all(), [
            'Id'    => 'required|numeric',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            $this->addMessage($errors->first(), 'GLCO0004', self::$ERROR);
            return $this->json(false, 'bool');
        }

        try {
            
            $laboOrderRepository = new LaboOrderRepository;
            $results = $laboOrderRepository->getListCommentOrder($request->all());
            $tree = [];
            if ($results) {
                $items = [];
                foreach ($results as $item) {
                    $item = (array) $item;
                    $item['OrderCommentAttachment'] = $laboOrderRepository->getListCommentOrderAttachment($item['OrderCommentId']);
                    $item['children'] = [];
                    $items[$item['OrderCommentId']] = $item;
                }
                foreach ($items as $id => $item) {
                    if ($item['ParentOrderCommentId']) {
                        $parentId = $item['ParentOrderCommentId'];
                        if (isset($items[$parentId])) {
                            $items[$parentId]['children'][] = &$items[$id];
                        }
                    } else {
                        $tree[] = &$items[$id];
                    }
                }
                self::sortTreeByOrderCommentId($tree);
            }
            return $this->json([$this->formatData('ListCommentOrder',$tree)]);

        } catch (\Exception $e) {

            Log::error("ListCommentOrder errors", [$e->getMessage()]);
            return $this->json([$this->formatData('ListCommentOrder',[])]);

        }
    }

    public function sortTreeByOrderCommentId(array &$nodes) {

        foreach ($nodes as &$node) {
            if (!empty($node['children'])) {
                usort($node['children'], function ($a, $b) {
                    return $a['OrderCommentId'] <=> $b['OrderCommentId'];
                });
                self::sortTreeByOrderCommentId($node['children']);
            }
        }
    }

    public function countLaboOrderRating(Request $request) {

        try {
            
            $laboOrderRepository = new LaboOrderRepository;
            $result = $laboOrderRepository->countLaboOrderRating($request->all());

            if ($result) {
                return $this->json([$this->formatData('CountLaboOrderRating',$result)]);
            }
            return $this->json([$this->formatData('CountLaboOrderRating',[0])]);
            
        } catch (\Exception $e) {

            Log::error("CountLaboOrderRating errors", [$e->getMessage()]);
            return $this->json([$this->formatData('CountLaboOrderRating',[])]);
        }
    }

    public function getLaboOrderStyle(Request $request) {

        try {
            
            $laboOrderRepository = new LaboOrderRepository;
            $result = $laboOrderRepository->getLaboOrderStyle();

            if ($result) {
                return $this->json([$this->formatData('LaboOrderStyle',$result)]);
            }
            return $this->json([$this->formatData('LaboOrderStyle',[])]);
            
        } catch (\Exception $e) {

            Log::error("LaboOrderStyle errors", [$e->getMessage()]);
            return $this->json([$this->formatData('LaboOrderStyle',[])]);
        }
    }
}