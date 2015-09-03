<?php

require_once 'StopWords.php';

class SearchQuery {

    private $fields = [];
    private $search_query = '';
    private $min_term_freq = 0;
    private $minimum_should_match = '';
    private $max_query_terms = 0;
    private $post;

    public function setParamsByWPPost(WP_Post $post)
    {
        $this->post = $post;

        $terms = wp_get_post_terms($post->ID);

        if (sizeof($terms) > 0) {
            $this->setParamsByTerms($terms, $post);
        } else {
            $this->setParamsByContent($post);
        }
    }

    private function setParamsByTerms(Array $terms, WP_Post $post)
    {

        $categories = get_the_category($post->ID);
        $terms = array_merge($terms, $categories);
        $tags = get_terms_name($terms);

        $this->fields = ["terms.post_tag.name", "post_title"];

        $this->search_query = implode(" ", $tags);
        $this->min_term_freq = 1;
        $this->minimum_should_match = '20%';
        $this->max_query_terms = sizeof($tags);

    }

    private function setParamsByContent(WP_Post $post)
    {
        $this->fields = ["post_title", "post_content"];
        $this->min_term_freq = 4;
        $this->minimum_should_match = '60%';
        $this->max_query_terms = 25;

        $stop_words = new StopWords();
        $this->search_query = $stop_words->stripStopWords($post->title. ' '.$post->post_content);
    }

    /**
     * @return array
     */
    public function getFields()
    {
        return $this->fields;
    }

    /**
     * @return string
     */
    public function getSearchQuery()
    {
        return $this->search_query;
    }

    /**
     * @return int
     */
    public function getMinTermFreq()
    {
        return $this->min_term_freq;
    }

    /**
     * @return string
     */
    public function getMinimumShouldMatch()
    {
        return $this->minimum_should_match;
    }

    /**
     * @return int
     */
    public function getMaxQueryTerms()
    {
        return $this->max_query_terms;
    }

}