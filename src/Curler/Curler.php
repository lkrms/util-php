<?php

declare(strict_types=1);

namespace Lkrms\Curler;

use Lkrms\Convert;

class Curler
{
    /**
     * @var string
     */
    private $BaseUrl;

    /**
     * @var CurlerHeaders
     */
    private $Headers;

    /**
     * @var array
     */
    private $LastCurlInfo;

    /**
     * @var string
     */
    private $LastResponse;

    /**
     * @var int
     */
    private $LastResponseCode;

    /**
     * @var array
     */
    private $LastResponseHeaders;

    /**
     * @var bool
     */
    private $Debug = false;

    /**
     * @var bool
     */
    private $NoNumericKeys = false;

    private static $Curl;

    private static $ResponseHeaders;

    public function __construct(string $baseUrl, CurlerHeaders $headers)
    {
        $this->BaseUrl = $baseUrl;
        $this->Headers = $headers;

        if (is_null(self::$Curl))
        {
            self::$Curl = curl_init();

            // don't send output to browser
            curl_setopt(self::$Curl, CURLOPT_RETURNTRANSFER, true);

            // collect response headers
            curl_setopt(self::$Curl, CURLOPT_HEADERFUNCTION,

            function ($curl, $header)
            {
                $split = explode(":", $header, 2);

                if (count($split) == 2)
                {
                    list ($name, $value) = $split;

                    // header field names are case-insensitive
                    $name  = strtolower($name);
                    $value = trim($value);
                    self::$ResponseHeaders[$name] = $value;
                }

                return strlen($header);
            }

            );
        }
    }

    private function HttpBuildQuery($queryData): string
    {
        $query = http_build_query($queryData);

        if ($this->NoNumericKeys)
        {
            $query = preg_replace("/(^|&)([^=]*%5B)[0-9]+(%5D[^=]*)/", "\$1\$2\$3", $query);
        }

        return $query;
    }

    private function Initialise($requestType, ?array $queryString)
    {
        if (empty($queryString))
        {
            $query = "";
        }
        else
        {
            $query = "?" . $this->HttpBuildQuery($queryString);
        }

        curl_setopt(self::$Curl, CURLOPT_URL, $this->BaseUrl . $query);

        switch ($requestType)
        {
            case "GET":

                curl_setopt(self::$Curl, CURLOPT_CUSTOMREQUEST, null);
                curl_setopt(self::$Curl, CURLOPT_HTTPGET, true);

                break;

            case "POST":

                curl_setopt(self::$Curl, CURLOPT_CUSTOMREQUEST, null);
                curl_setopt(self::$Curl, CURLOPT_POST, true);

                break;

            default:

                // allows DELETE, PATCH etc.
                curl_setopt(self::$Curl, CURLOPT_CUSTOMREQUEST, $requestType);
        }

        $this->Headers->UnsetHeader("Content-Type");

        // in debug mode, collect request headers
        curl_setopt(self::$Curl, CURLINFO_HEADER_OUT, $this->Debug);
    }

    private function SetData(?array $data, bool $asJson)
    {
        if ( ! is_null($data))
        {
            $hasFile = false;
            array_walk_recursive($data,

            function (&$value, $key) use (&$hasFile)
            {
                if ($value instanceof CurlerFile)
                {
                    $value   = $value->GetCurlFile();
                    $hasFile = true;
                }
            }

            );

            if ($hasFile)
            {
                $query = $data;
            }
            elseif ($asJson)
            {
                $this->Headers->SetHeader("Content-Type", "application/json");
                $query = json_encode($data);
            }
            else
            {
                $query = $this->HttpBuildQuery($data);
            }

            curl_setopt(self::$Curl, CURLOPT_POSTFIELDS, $query);
        }
    }

    private function Execute(): string
    {
        // add headers for authentication etc.
        curl_setopt(self::$Curl, CURLOPT_HTTPHEADER, $this->Headers->GetHeaders());

        // clear any previous response headers
        self::$ResponseHeaders = [];

        // execute the request
        $result = curl_exec(self::$Curl);

        // save transfer information
        $this->LastCurlInfo        = curl_getinfo(self::$Curl);
        $this->LastResponseHeaders = self::$ResponseHeaders;

        if ($result === false)
        {
            $this->LastResponse     = null;
            $this->LastResponseCode = null;
            throw new CurlerException($this, "cURL error: " . curl_error(self::$Curl));
        }

        $this->LastResponse     = $result;
        $this->LastResponseCode = (int)curl_getinfo(self::$Curl, CURLINFO_RESPONSE_CODE);

        if ($this->LastResponseCode >= 400)
        {
            throw new CurlerException($this, "HTTP error " . ($this->LastResponseHeaders["status"] ?? $this->LastResponseCode));
        }

        return $result;
    }

    public function GetBaseUrl(): string
    {
        return $this->BaseUrl;
    }

    public function GetHeaders(): CurlerHeaders
    {
        return $this->Headers;
    }

    public function GetDebug(): bool
    {
        return $this->Debug;
    }

    public function GetNumericKeys(): bool
    {
        return ! $this->NoNumericKeys;
    }

    public function EnableDebug()
    {
        $this->Debug = true;
    }

    public function DisableDebug()
    {
        $this->Debug = false;
    }

    public function EnableNumericKeys()
    {
        $this->NoNumericKeys = false;
    }

    public function DisableNumericKeys()
    {
        $this->NoNumericKeys = true;
    }

    public function Get(array $queryString = null): string
    {
        $this->Initialise("GET", $queryString);

        return $this->Execute();
    }

    public function GetJson(array $queryString = null)
    {
        return json_decode($this->Get($queryString), true);
    }

    public function Post(array $data = null, array $queryString = null, bool $dataAsJson = true): string
    {
        $this->Initialise("POST", $queryString);
        $this->SetData($data, $dataAsJson);

        return $this->Execute();
    }

    public function PostJson(array $data = null, array $queryString = null, bool $dataAsJson = true)
    {
        return json_decode($this->Post($data, $queryString, $dataAsJson), true);
    }

    public function RawPost(string $data, string $contentType, array $queryString = null): string
    {
        $this->Initialise("POST", $queryString);
        $this->Headers->SetHeader("Content-Type", $contentType);
        curl_setopt(self::$Curl, CURLOPT_POSTFIELDS, $data);

        return $this->Execute();
    }

    public function RawPostJson(string $data, string $contentType, array $queryString = null)
    {
        return json_decode($this->RawPost($data, $contentType, $queryString), true);
    }

    public function Put(array $data = null, array $queryString = null, bool $dataAsJson = true): string
    {
        $this->Initialise("PUT", $queryString);
        $this->SetData($data, $dataAsJson);

        return $this->Execute();
    }

    public function PutJson(array $data = null, array $queryString = null, bool $dataAsJson = true)
    {
        return json_decode($this->Put($data, $queryString, $dataAsJson), true);
    }

    public function Patch(array $data = null, array $queryString = null, bool $dataAsJson = true): string
    {
        $this->Initialise("PATCH", $queryString);
        $this->SetData($data, $dataAsJson);

        return $this->Execute();
    }

    public function PatchJson(array $data = null, array $queryString = null, bool $dataAsJson = true)
    {
        return json_decode($this->Patch($data, $queryString, $dataAsJson), true);
    }

    public function Delete(array $data = null, array $queryString = null, bool $dataAsJson = true): string
    {
        $this->Initialise("DELETE", $queryString);
        $this->SetData($data, $dataAsJson);

        return $this->Execute();
    }

    public function DeleteJson(array $data = null, array $queryString = null, bool $dataAsJson = true)
    {
        return json_decode($this->Delete($data, $queryString, $dataAsJson), true);
    }

    public function GetLastCurlInfo(): ?array
    {
        return $this->LastCurlInfo;
    }

    public function GetLastResponse(): ?string
    {
        return $this->LastResponse;
    }

    public function GetLastResponseCode(): ?int
    {
        return $this->LastResponseCode;
    }

    public function GetLastResponseHeaders(): ?array
    {
        return $this->LastResponseHeaders;
    }

    /**
     * Follow HTTP `Link` headers to retrieve and merge paged JSON data
     *
     * @param array $queryString
     * @return array All returned entities.
     */
    public function GetAllLinked(array $queryString = null): array
    {
        $this->Initialise("GET", $queryString);
        $entities = [];
        $nextUrl  = null;

        do
        {
            if ($nextUrl)
            {
                curl_setopt(self::$Curl, CURLOPT_URL, $nextUrl);
                $nextUrl = null;
            }

            // collect data from response and move on to next page
            $result   = json_decode($this->Execute(), true);
            $entities = array_merge($entities, $result);

            if (preg_match("/<([^>]+)>;\\s*rel=(['\"])next\\2/", $this->LastResponseHeaders["link"] ?? "", $matches))
            {
                $nextUrl = $matches[1];
            }
        }
        while ($nextUrl);

        return $entities;
    }

    /**
     * Follow `$result['links']['next']` to retrieve and merge paged JSON data
     *
     * @param string $entityName Data is retrieved from `$result[$entityName]`.
     * @param array $queryString
     * @return array All returned entities.
     */
    public function GetAllLinkedByEntity($entityName, array $queryString = null): array
    {
        $this->Initialise("GET", $queryString);
        $entities = [];
        $nextUrl  = null;

        do
        {
            if ($nextUrl)
            {
                curl_setopt(self::$Curl, CURLOPT_URL, $nextUrl);
            }

            // collect data from response and move on to next page
            $result   = json_decode($this->Execute(), true);
            $entities = array_merge($entities, $result[$entityName]);
            $nextUrl  = $result["links"]["next"] ?? null;
        }
        while ($nextUrl);

        return $entities;
    }

    public function GetAllLinkedByOData(array $queryString = null, string $prefix = null)
    {
        $this->Initialise("GET", $queryString);
        $entities = [];
        $nextUrl  = null;

        do
        {
            if ($nextUrl)
            {
                curl_setopt(self::$Curl, CURLOPT_URL, $nextUrl);
            }

            // collect data from response and move on to next page
            $result = json_decode($this->Execute(), true);

            if (is_null($prefix))
            {
                if ($this->LastResponseHeaders["odata-version"] == "4.0")
                {
                    $prefix = "@odata.";
                }
                else
                {
                    $prefix = "@";
                }
            }

            $entities = array_merge($entities, $result["value"]);
            $nextUrl  = $result[$prefix . "nextLink"] ?? null;
        }
        while ($nextUrl);

        return $entities;
    }

    private static function CollateNested($data, array $path = null, array & $entities = null)
    {
        if (empty($path))
        {
            $entities = array_merge($entities ?? [], Convert::AnyToArray($data));
        }
        elseif (is_array($data))
        {
            foreach ($data as $nested)
            {
                self::CollateNested($nested, $path, $entities);
            }
        }
        else
        {
            $field = array_shift($path);

            // gracefully skip missing data
            if (isset($data->$field))
            {
                self::CollateNested($data->$field, $path, $entities);
            }
        }
    }

    public function GetByGraphQL(string $query, array $variables = null, string $entityPath = null, string $pagePath = null, callable $filter = null): array
    {
        if ( ! is_null($pagePath) && ! (($variables["first"] ?? null) && array_key_exists("after", $variables)))
        {
            throw new CurlerException($this, "\$first and \$after variables are required for pagination");
        }

        $entities  = [];
        $nextQuery = [
            "query"     => $query,
            "variables" => $variables,
        ];

        do
        {
            $result = json_decode($this->Post($nextQuery));

            if ( ! isset($result->data))
            {
                throw new CurlerException($this, "no data returned");
            }

            $nextQuery = null;
            $objects   = [];
            self::CollateNested($result->data, is_null($entityPath) ? null : explode(".", $entityPath), $objects);

            if ($filter)
            {
                $objects = array_filter($objects, $filter);
            }

            $entities = array_merge($entities, $objects);

            if ( ! is_null($pagePath))
            {
                $page = [];
                self::CollateNested($result->data, explode(".", $pagePath), $page);

                if (count($page) != 1 || ! isset($page[0]->pageInfo->endCursor) || ! isset($page[0]->pageInfo->hasNextPage))
                {
                    throw new CurlerException($this, "paginationPath did not resolve to a single object with pageInfo.endCursor and pageInfo.hasNextPage fields");
                }

                if ($page[0]->pageInfo->hasNextPage)
                {
                    $variables["after"] = $page[0]->pageInfo->endCursor;
                    $nextQuery          = [
                        "query"     => $query,
                        "variables" => $variables,
                    ];
                }
            }
        }
        while ($nextQuery);

        return $entities;
    }
}

