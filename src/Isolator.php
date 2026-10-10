<?php

namespace JDZ\Search;

/**
 * @author Joffrey Demetz <joffrey.demetz@gmail.com>
 */
class Isolator
{
  private int $numWordsAround = 10;
  private string $regex = '';
  private string $content = '';

  public function setNumWordsAround(int $numWordsAround): static
  {
    $this->numWordsAround = $numWordsAround;
    return $this;
  }

  public function setRegexFromSearchArray(array $searchArray): static
  {
    $terms = array_filter($searchArray, fn($term) => '' !== $term && mb_check_encoding($term, 'UTF-8'));
    $terms = array_map(fn($term) => preg_quote($term, '/'), $terms);

    if (!$terms) {
      $this->regex = '';
      return $this;
    }

    $regex = ''
      . '(.*)'
      . '('
      . implode('|', $terms)
      . ')'
      . '(.*)';

    $this->regex = $regex;
    return $this;
  }

  public function setContent(string $content): static
  {
    $this->content = $this->cleanContent($content);
    return $this;
  }

  public function getContent(): string
  {
    return $this->content;
  }

  public function highlight(): static
  {
    $paragraphs = [];

    libxml_use_internal_errors(true);
    $root = new \DOMDocument('1.0', 'utf-8');
    $root->preserveWhiteSpace = false;
    // libxml reads HTML without a charset as Latin-1: hand it ASCII, the rest as numeric entities
    $html = mb_encode_numericentity($this->content, [0x80, 0x10FFFF, 0, 0x1FFFFF], 'UTF-8');
    $root->loadHtml('<html>' . $html . '</html>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

    $box = $root->getElementsByTagName('html');

    if ($box->length) {
      foreach ($box as $item) {
        foreach ($item->childNodes as $element) {
          $this->extractParagraphs($element, $paragraphs);
        }
      }
    }

    $found = [];
    if ('' !== $this->regex) {
      foreach ($paragraphs as $p) {
        if (preg_match('/' . $this->regex . '/isu', $p)) {
          $test = preg_replace_callback('/' . $this->regex . '/isu', [$this, 'highlightCallback'], $p);
          if ($test) {
            $found[] = $test;
          }
        }
      }
    }

    if ($found) {
      $this->content = '<p>' . implode(' ', $found) . '</p>';
    } else {
      $this->content = '<p>' . implode(' [...] ', array_map($this->escape(...), $paragraphs)) . '</p>';
    }

    $this->content = mb_ereg_replace('\s\s+', ' ', $this->content);
    $this->content = str_replace('&hellip; &hellip;', '&hellip;', $this->content);

    return $this;
  }

  private function highlightCallback(array $m): string
  {
    $before  = $m[1];
    $content = $m[2];
    $after   = $m[3];

    $words = [];
    $cut = false;

    if (!empty($before)) {
      $beforeWords = explode(' ', $before);

      $w = 0;
      $cbw = count($beforeWords);
      for ($i = $cbw - 1; $i >= 0 && $w <= $this->numWordsAround; $i--) {
        if ($w > ($this->numWordsAround - 4) && 1 === preg_match("/[\):,;\.]+/", $beforeWords[$i])) {
          break;
        }
        array_unshift($words, $this->escape($beforeWords[$i]));
        $w++;
      }

      $cut = $cbw > $w;
    }

    // the last word before the match is '' when the match starts a word, else the
    // start of the word the match is in: glued to the highlight
    $excerpt = implode(' ', $words) . '<strong>' . $this->escape($content) . '</strong>';

    if ($cut) {
      $excerpt = '&hellip; ' . $excerpt;
    }

    if (!empty($after)) {
      $excerpt .= ' &hellip;';
    }

    return $excerpt;
  }

  /**
   * Text read from the DOM has its entities decoded: escape it again before it
   * goes back into the excerpt, which is HTML.
   */
  private function escape(string $text): string
  {
    return htmlspecialchars($text, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
  }

  private function extractParagraphs(\DOMNode $root, array &$paragraphs = []): array
  {
    $array = [];

    if ($root->nodeType == XML_ELEMENT_NODE) {
      switch ($root->nodeName) {
        case 'h1':
        case 'h2':
        case 'h3':
        case 'h4':
        case 'h5':
        case 'h6':
        case 'p':
        case 'ul':
        case 'ol':
        case 'li':
        case 'div':
        case 'blockquote':
        case 'figure':
        case 'figcaption':
          break;

        default:
          return $array;
      }

      $array['_type'] = $root->nodeName;
      if ($root->hasChildNodes()) {
        $children = $root->childNodes;

        for ($i = 0, $n = $children->length; $i < $n; $i++) {
          $child = $this->extractParagraphs($children->item($i), $paragraphs);

          if (!empty($child)) {
            $array['_children'][] = $child;
          }
        }
      }

      return $array;
    }

    if ($root->nodeType == XML_TEXT_NODE || $root->nodeType == XML_CDATA_SECTION_NODE) {
      $value = $root->nodeValue;

      if ('' !== trim($value)) {
        $array['_type'] = '_text';
        $array['_content'] = $value;
        $paragraphs[] = $value;
      }
    }

    return $array;
  }

  private function cleanContent(string $content): string
  {
    $content = str_replace("'", "'", $content);
    $content = str_replace("œ", "&oelig;", $content);
    $content = str_replace("&nbsp;", " ", $content);
    $content = mb_ereg_replace('\s\s+', ' ', $content);
    $content = mb_ereg_replace(' style="[^"]+"', '', $content);
    $content = mb_ereg_replace(' class="[^"]+"', '', $content);
    $content = trim($content);
    $content = mb_convert_encoding($content, 'UTF-8', 'auto');
    $content = strip_tags($content, '<div><blockquote><figure><figcaption><p><ul><ol><li><h1><h2><h3><h4><h5><h6>');

    return $content;
  }
}
