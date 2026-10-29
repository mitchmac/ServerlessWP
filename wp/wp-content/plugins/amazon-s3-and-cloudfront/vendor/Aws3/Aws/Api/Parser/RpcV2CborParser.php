<?php

namespace DeliciousBrains\WP_Offload_Media\Aws3\Aws\Api\Parser;

use DeliciousBrains\WP_Offload_Media\Aws3\Aws\Api\Cbor\CborDecoder;
use DeliciousBrains\WP_Offload_Media\Aws3\Aws\Api\Service;
use DeliciousBrains\WP_Offload_Media\Aws3\Aws\Api\StructureShape;
use DeliciousBrains\WP_Offload_Media\Aws3\Psr\Http\Message\StreamInterface;
/**
 * Parses responses according to Smithy RPC V2 CBOR protocol standards.
 *
 * https://smithy.io/2.0/additional-specs/protocols/smithy-rpc-v2.html
 *
 * @internal
 */
final class RpcV2CborParser extends AbstractRpcV2Parser
{
    /** @var string  */
    protected static string $smithyProtocol = 'rpc-v2-cbor';
    /** @var CborDecoder  */
    private CborDecoder $decoder;
    use RpcV2ParserTrait;
    /**
     * @param Service $api Service description
     */
    public function __construct(Service $api)
    {
        $this->decoder = new CborDecoder();
        parent::__construct($api);
    }
    /**
     * @param StreamInterface $stream
     * @param StructureShape $member
     * @param $response
     *
     * @return mixed
     */
    public function parseMemberFromStream(StreamInterface $stream, StructureShape $member, $response) : mixed
    {
        return $this->resolveOutputShape($member, $this->parseCbor($stream, $response));
    }
}
