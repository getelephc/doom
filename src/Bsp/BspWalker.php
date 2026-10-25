<?php

namespace Showcases\Doom\Bsp;

use Showcases\Doom\Data\Node;
use Showcases\Doom\Map\MapData;

// BSP traversal. A point on the positive side of a node's partition is in front of
// it, which the right child covers (DOOM's R_PointOnSide).
final class BspWalker {
    // subsector indices front to back for the last walk; only the first $count are valid
    public buffer<int> $order;
    public int $count;
    public buffer<Node> $nodes;
    public int $nodeCount;
    public int $subSectorCount;
    public int $viewX;
    public int $viewY;

    public function __construct() {
        $this->order = buffer_new<int>(1);
        $this->count = 0;
        $this->nodes = buffer_new<Node>(1);
        $this->nodeCount = 0;
        $this->subSectorCount = 0;
        $this->viewX = 0;
        $this->viewY = 0;
    }

    public function walk(MapData $map, int $x, int $y): int {
        $this->count = 0;
        if (!$map->isValid() || $map->subSectorCount <= 0) {
            return 0;
        }
        if (buffer_len($this->order) < $map->subSectorCount) {
            $this->order = buffer_new<int>($map->subSectorCount);
        }
        if ($map->nodeCount <= 0) {
            for ($i = 0; $i < $map->subSectorCount; $i++) {
                $this->order[$i] = $i;
            }
            $this->count = $map->subSectorCount;
            return $this->count;
        }

        $this->nodes = $map->nodes;
        $this->nodeCount = $map->nodeCount;
        $this->subSectorCount = $map->subSectorCount;
        $this->viewX = $x;
        $this->viewY = $y;
        int $root = $map->nodeCount - 1;
        $this->visitNode($root);
        return $this->count;
    }

    public function visitNode(int $nodeIndex): void {
        if ($nodeIndex < 0 || $nodeIndex >= $this->nodeCount) {
            return;
        }
        int $nearChild = $this->nodes[$nodeIndex]->left_child;
        int $farChild = $this->nodes[$nodeIndex]->right_child;
        if ($this->sideOf($nodeIndex, $this->viewX, $this->viewY) > 0) {
            $nearChild = $this->nodes[$nodeIndex]->right_child;
            $farChild = $this->nodes[$nodeIndex]->left_child;
        }
        $this->visitChild($nearChild);
        $this->visitChild($farChild);
    }

    public function visitChild(int $child): void {
        if ($child >= 32768) {
            int $subSectorIndex = $child - 32768;
            if ($subSectorIndex < $this->subSectorCount) {
                $this->order[$this->count] = $subSectorIndex;
                $this->count++;
            }
            return;
        }
        $this->visitNode($child);
    }

    // Subsector containing a point, descending on the point's side of each partition.
    public function subSectorAt(MapData $map, int $x, int $y): int {
        if (!$map->isValid() || $map->subSectorCount <= 0) {
            return -1;
        }
        if ($map->nodeCount <= 0) {
            return 0;
        }
        $this->nodes = $map->nodes;
        $this->nodeCount = $map->nodeCount;
        int $nodeIndex = $map->nodeCount - 1;
        for ($depth = 0; $depth < $map->nodeCount; $depth++) {
            int $child = $this->nodes[$nodeIndex]->left_child;
            if ($this->sideOf($nodeIndex, $x, $y) > 0) {
                $child = $this->nodes[$nodeIndex]->right_child;
            }
            if ($child >= 32768) {
                int $subSectorIndex = $child - 32768;
                if ($subSectorIndex >= $map->subSectorCount) {
                    return -1;
                }
                return $subSectorIndex;
            }
            if ($child >= $map->nodeCount) {
                return -1;
            }
            $nodeIndex = $child;
        }
        return -1;
    }

    public function sideOf(int $nodeIndex, int $px, int $py): int {
        int $relX = $px - $this->nodes[$nodeIndex]->x;
        int $relY = $py - $this->nodes[$nodeIndex]->y;
        int $first = $relX * $this->nodes[$nodeIndex]->dy;
        int $second = $relY * $this->nodes[$nodeIndex]->dx;
        int $side = $first - $second;
        return $side;
    }
}
