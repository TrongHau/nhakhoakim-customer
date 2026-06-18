<?php 
//namespace App\libs\Remote\Queue;
//
//use PhpAmqpLib\Message\AMQPMessage;
//use Remote\Rpc;
//use \Exception AS Exception;
//
///**
// *
// * @author giangdn
// *
// */
//class Message extends AMQPMessage
//{
//    /**
//     * @var Rpc[]
//     */
//    protected $rpcs = [];
//
//    /**
//     * @return Message
//     */
//    public function weakup()
//    {
//        try {
//            if ($strBody = $this->getBody()) {
//                $jsonBody = json_decode($strBody);
//
//                if (isset($jsonBody->rpcs) && count($jsonBody->rpcs)) {
//                    foreach ($jsonBody->rpcs AS $rpc) $this->rpcs[] = Rpc::init($rpc);
//                }
//            }
//        } catch (Exception $e) {
//           app('log')->error($e->getMessage());
//        }
//        return $this;
//    }
//
//    /**
//     * @param array $rpcs
//     * @return Message
//     */
//    public function setRpcs($rpcs)
//    {
//        $this->rpcs = $rpcs;
//        return $this;
//    }
//
//    /**
//     * @param Rpc $rpc
//     * @return Message
//     */
//    public function addRpc($rpc)
//    {
//        $this->rpcs[] = $rpc;
//        return $this;
//    }
//
//    /**
//     * @return Message
//     */
//    public function serialize()
//    {
//        if (count($this->rpcs)) {
//            $rpcs = [];
//            foreach ($this->rpcs AS $rpc) {
//                $rpcs[] = $rpc->serialize();
//            }
//
//            $this->setBody(json_encode(['text' => $this->getBody(), 'rpcs' => $rpcs ]));
//        }
//    }
//}