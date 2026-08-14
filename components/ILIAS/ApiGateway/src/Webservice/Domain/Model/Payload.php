<?php

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

declare(strict_types=1);

namespace ILIAS\ApiGateway\Webservice\Domain\Model;

readonly class Payload
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        private mixed $data = null,
        private array $headers = [],
        private ?string $body = null,
    ) {
    }

    /**
     * raw content of the payload
     */
    public function getData(): mixed
    {
        return $this->data;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getBody(): string
    {
        return $this->body ?? '';
    }

    public function withHeader(string $name, string $value): self
    {
        $newHeaders = $this->headers;
        $newHeaders[$name] = $value;

        return new self(
            $this->data,
            $newHeaders,
            $this->body,
        );
    }

    public function withBody(?string $body): self
    {
        return new self(
            $this->data,
            $this->headers,
            $body,
        );
    }
}
