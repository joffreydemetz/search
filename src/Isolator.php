<?php

namespace JDZ\Search;

/**
 * The excerpt of a result: the paragraphs of its content that match the
 * search terms, each cut to a window of words around its first match, every
 * match of the window in a <strong>.
 *
 * @author Joffrey Demetz <joffrey.demetz@gmail.com>
 */
class Isolator
{
  private int $numWordsAround = 10;
  /** the terms alternation, quoted for a regex; '' without a usable term */
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

    $this->regex = $terms ? implode('|', $terms) : '';

    return $this;
  }

  /**
   * Whether a usable term remains (not empty, valid UTF-8).
   */
  public function hasTerms(): bool
  {
    return '' !== $this->regex;
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

    if ($this->hasTerms()) {
      foreach ($paragraphs as $p) {
        if (null !== ($excerpt = $this->excerpt($p))) {
          $found[] = $excerpt;
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

  /**
   * The excerpt of a paragraph: numWordsAround words before its first match
   * (fewer after a sentence break near the window's edge) and after it, every
   * match in the window highlighted; null when nothing matches.
   *
   * The pattern used to be a greedy (.*)(term)(.*): only the last match was
   * highlighted and whatever followed it was always cut.
   */
  private function excerpt(string $paragraph): ?string
  {
    $pattern = '/(' . $this->regex . ')/isu';

    if (1 !== preg_match($pattern, $paragraph, $m, PREG_OFFSET_CAPTURE)) {
      return null;
    }

    $before = substr($paragraph, 0, $m[0][1]);
    $rest = substr($paragraph, $m[0][1]);

    // the words before: the last one is '' when the match starts a word, else
    // the start of the word the match is in, glued to the highlight
    $words = [];
    $cutBefore = false;

    if ('' !== $before) {
      $beforeWords = explode(' ', $before);
      $w = 0;
      $cbw = count($beforeWords);

      for ($i = $cbw - 1; $i >= 0 && $w <= $this->numWordsAround; $i--) {
        if ($w > ($this->numWordsAround - 4) && 1 === preg_match("/[\):,;\.]+/", $beforeWords[$i])) {
          break;
        }

        array_unshift($words, $beforeWords[$i]);
        $w++;
      }

      $cutBefore = $cbw > $w;
    }

    // the match and the words after it
    $afterWords = explode(' ', $rest);
    $cutAfter = count($afterWords) > $this->numWordsAround + 1;
    $afterWords = array_slice($afterWords, 0, $this->numWordsAround + 1);

    $excerpt = $this->mark(implode(' ', $words) . implode(' ', $afterWords), $pattern);

    if ($cutBefore) {
      $excerpt = '&hellip; ' . $excerpt;
    }

    if ($cutAfter) {
      $excerpt .= ' &hellip;';
    }

    return $excerpt;
  }

  /**
   * The text escaped, every match of the pattern in a <strong>.
   */
  private function mark(string $text, string $pattern): string
  {
    $pieces = preg_split($pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    $marked = '';

    foreach ($pieces as $i => $piece) {
      $marked .= 1 === $i % 2 ? '<strong>' . $this->escape($piece) . '</strong>' : $this->escape($piece);
    }

    return $marked;
  }

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
    // a curly apostrophe reads as the straight one of the search term (the replace used to be a no-op)
    $content = str_replace("\u{2019}", "'", $content);
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
