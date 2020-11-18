<?php

namespace SearchEngine;

/**
 * SearchEngine Processor
 *
 * @version 0.2.0
 * @author Teppo Koivula <teppo.koivula@gmail.com>
 * @license Mozilla Public License v2.0 https://mozilla.org/MPL/2.0/
 */
class Processor extends Base {

    /**
     * Process index for storage
     *
     * This method converts an index array to a string, sanitizes it removing content we don't want in the index (tags
     * etc.) and finally appends an index of links to the index string.
     *
     * @param array $index Index as an array.
     * @return string Processed index string.
     */
    public function processIndex(array $index): string {
        $processed_index = '';
        $index = array_filter($index);
        if (!empty($index)) {
            $meta_index = [];
            foreach ($index as $index_key => $index_value) {
                // Identify and capture values belonging to the meta index (non-field values).
                if (strpos($index_key, Indexer::META_PREFIX) === 0) {
                    $meta_key = substr($index_key, strlen(Indexer::META_PREFIX));
                    if (strpos($meta_key, '.')) {
                        // Note: index is always a flat assoc array, but a key with multiple dots means that the value
                        // needs to be stored as a multi-dimensional array.
                        $key_parts = explode('.', $meta_key);
                        $meta_parent = array_shift($key_parts);
                        // Remove numeric key parts. This is done so that one can search for specific value in specific
                        // page ref field (page_ref=1234). This also gets rid of possible duplicates, which we wouldn't
                        // need anyway (searching for page_ref.0.1234 or page_ref.1.1234 is not a common use case.)
                        $key_parts = array_filter($key_parts, function($value) {
                            return !is_numeric($value);
                        });
                        $meta_name = implode('.', $key_parts);
                        if (empty($meta_index[$meta_parent])) {
                            $meta_index[$meta_parent] = [];
                        }
                        $meta_index[$meta_parent][] = $meta_name . $index_value;
                    } else {
                        $meta_index[$meta_key] = $index_value;
                    }
                    unset($index[$index_key]);
                }
            }
            $processed_index = implode(' ... ', $index);
            $url_index = $this->getURLIndex($processed_index);
            if (!empty($url_index)) {
                $meta_index['urls'] = $url_index;
            }
            if (!empty($meta_index)) {
                // Make sure that index arrays don't contain duplicate or empty values.
                foreach ($meta_index as $key => $value) {
                    if (is_array($value)) {
                        $meta_index[$key] = array_values(array_unique(array_filter($value)));
                    }
                }
            }
            $processed_index = str_replace('<', ' <', $processed_index);
            $processed_index = strip_tags($processed_index);
            // Note: "u" flag fixes a potential macOS PCRE UTF-8 issue, https://github.com/silverstripe/silverstripe-framework/issues/7132
            $processed_index = preg_replace('/\s+/u', ' ', $processed_index);
            $processed_index .= "\n" . (empty($meta_index) ? '{}' : json_encode($meta_index, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
        return $processed_index;
    }

    /**
     * Create an index of URLs
     *
     * Find URLs in field data and return them as an array. This allows us to search for links with `link:https://URL`
     * syntax (link prefix is configurable but defaults to "link:").
     *
     * @param string $data
     * @return array
     */
    protected function getURLIndex(string $data): array {
        $index = [];
        if (!empty($data) && preg_match_all('/href=([\"\'])(.*?)\1/i', $data, $matches)) {
            $link_prefix = $this->getOptions()['prefixes']['link'];
            foreach ($matches[2] as $link) {
                $index[] = $link_prefix . $link;
            }
        }
        return $index;
    }

}
