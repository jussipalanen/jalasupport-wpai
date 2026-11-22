<?php

/**
 * Minimal stand-ins for WordPress core classes used by unit tests.
 *
 * They implement only the behaviour the plugin relies on. Integration with
 * the real classes is covered by running the application.
 */
if (! class_exists('WP_Error')) {
    class WP_Error
    {
        /** @var array<string, list<string>> */
        public array $errors = [];

        /** @var array<string, mixed> */
        public array $error_data = [];

        public function __construct(string $code = '', string $message = '', mixed $data = '')
        {
            if ($code !== '') {
                $this->errors[$code][] = $message;

                if ($data !== '') {
                    $this->error_data[$code] = $data;
                }
            }
        }

        public function get_error_code(): string
        {
            return (string) (array_key_first($this->errors) ?? '');
        }

        public function get_error_message(string $code = ''): string
        {
            $code = $code !== '' ? $code : $this->get_error_code();

            return $this->errors[$code][0] ?? '';
        }

        public function get_error_data(string $code = ''): mixed
        {
            $code = $code !== '' ? $code : $this->get_error_code();

            return $this->error_data[$code] ?? null;
        }
    }
}

if (! class_exists('WP_Post')) {
    class WP_Post
    {
        public int $ID = 0;
        public string $post_author = '0';
        public string $post_title = '';
        public string $post_content = '';
        public string $post_status = 'publish';
        public string $post_type = 'post';
        public string $post_date_gmt = '0000-00-00 00:00:00';
        public string $post_modified_gmt = '0000-00-00 00:00:00';

        /**
         * @param  array<string, mixed>  $data
         */
        public function __construct(array $data = [])
        {
            foreach ($data as $key => $value) {
                $this->{$key} = $value;
            }
        }
    }
}

if (! class_exists('WP_REST_Request')) {
    class WP_REST_Request
    {
        /**
         * @param  array<string, mixed>  $params  Route/query parameters.
         * @param  array<string, mixed>  $json  JSON body parameters.
         */
        /**
         * @param  array<string, string>  $headers  Lower-case names with underscores, as WordPress normalizes them.
         */
        public function __construct(private array $params = [], private array $json = [], private array $headers = []) {}

        public function get_header(string $key): ?string
        {
            return $this->headers[strtolower(str_replace('-', '_', $key))] ?? null;
        }

        public function get_param(string $key): mixed
        {
            return $this->json[$key] ?? $this->params[$key] ?? null;
        }

        /** @return array<string, mixed> */
        public function get_json_params(): array
        {
            return $this->json;
        }

        /** @return array<string, mixed> */
        public function get_body_params(): array
        {
            return [];
        }
    }
}

if (! class_exists('WP_REST_Response')) {
    class WP_REST_Response
    {
        /** @var array<string, string> */
        public array $headers = [];

        public function __construct(public mixed $data = null, public int $status = 200) {}

        public function header(string $key, string $value): void
        {
            $this->headers[$key] = $value;
        }

        public function get_data(): mixed
        {
            return $this->data;
        }

        public function get_status(): int
        {
            return $this->status;
        }
    }
}

if (! class_exists('WP_REST_Server')) {
    class WP_REST_Server
    {
        public const READABLE = 'GET';
        public const CREATABLE = 'POST';
        public const EDITABLE = 'POST, PUT, PATCH';
        public const DELETABLE = 'DELETE';
    }
}
