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
