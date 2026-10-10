<?php
/**
 * Class TreeNode
 * Merepresentasikan sebuah simpul dalam struktur data Tree (Pohon).
 */
class TreeNode {
    public string $id;
    public string $name;
    public string $type; // 'ROOT', 'KELAS', 'GERBONG'
    public array $extra;
    /** @var TreeNode[] */
    public array $children = [];

    public function __construct(string $id, string $name, string $type, array $extra = []) {
        $this->id = $id;
        $this->name = $name;
        $this->type = $type;
        $this->extra = $extra;
    }

    /**
     * Menambahkan simpul anak (child node).
     * @param TreeNode $child
     * @return void
     */
    public function addChild(TreeNode $child): void {
        $this->children[] = $child;
    }

    /**
     * Mendapatkan semua simpul anak.
     * @return TreeNode[]
     */
    public function getChildren(): array {
        return $this->children;
    }
}

/**
 * Class Tree
 * Implementasi struktur data Tree (Pohon Hierarki) untuk memodelkan struktur kelas dan gerbong kereta.
 */
class Tree {
    private TreeNode $root;

    public function __construct(TreeNode $root) {
        $this->root = $root;
    }

    public function getRoot(): TreeNode {
        return $this->root;
    }

    /**
     * Traversal Pre-Order (Kunjungi Node -> Kunjungi Seluruh Anak).
     * @param TreeNode|null $node
     * @param array $result
     * @return array
     */
    public function preOrderTraversal(?TreeNode $node = null, array &$result = []): array {
        if ($node === null) {
            $node = $this->root;
        }

        $result[] = [
            'id' => $node->id,
            'name' => $node->name,
            'type' => $node->type,
            'extra' => $node->extra
        ];

        foreach ($node->children as $child) {
            $this->preOrderTraversal($child, $result);
        }

        return $result;
    }

    /**
     * Menghasilkan representasi string teks hierarki pohon bertingkat.
     * Berguna untuk rendering visual berbasis teks atau indentasi.
     * 
     * @param TreeNode|null $node
     * @param int $depth
     * @return string
     */
    public function renderHierarchy(?TreeNode $node = null, int $depth = 0): string {
        if ($node === null) {
            $node = $this->root;
        }

        $indent = str_repeat("    ", $depth);
        $prefix = $depth === 0 ? "🚂 " : ($depth === 1 ? "├── [Kelas] " : "│   └── [Gerbong] ");
        $output = $indent . $prefix . $node->name . "\n";

        foreach ($node->children as $child) {
            $output .= $this->renderHierarchy($child, $depth + 1);
        }

        return $output;
    }

    /**
     * Mencari simpul pohon berdasarkan ID secara rekursif (Depth-First Search).
     * @param string $id
     * @param TreeNode|null $node
     * @return TreeNode|null
     */
    public function findNodeById(string $id, ?TreeNode $node = null): ?TreeNode {
        if ($node === null) {
            $node = $this->root;
        }

        if ($node->id === $id) {
            return $node;
        }

        foreach ($node->children as $child) {
            $found = $this->findNodeById($id, $child);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /**
     * Factory method untuk membangun struktur Tree dari nested array (seperti dari Dataset).
     * @param array $data
     * @return Tree
     */
    public static function buildFromArray(array $data): Tree {
        $extra = [];
        foreach ($data as $k => $v) {
            if (!in_array($k, ['id', 'name', 'type', 'children'])) {
                $extra[$k] = $v;
            }
        }

        $root = new TreeNode($data['id'], $data['name'], $data['type'], $extra);

        if (isset($data['children']) && is_array($data['children'])) {
            self::attachChildrenRecursive($root, $data['children']);
        }

        return new Tree($root);
    }

    private static function attachChildrenRecursive(TreeNode $parent, array $childrenData): void {
        foreach ($childrenData as $childData) {
            $extra = [];
            foreach ($childData as $k => $v) {
                if (!in_array($k, ['id', 'name', 'type', 'children'])) {
                    $extra[$k] = $v;
                }
            }

            $childNode = new TreeNode($childData['id'], $childData['name'], $childData['type'], $extra);
            $parent->addChild($childNode);

            if (isset($childData['children']) && is_array($childData['children'])) {
                self::attachChildrenRecursive($childNode, $childData['children']);
            }
        }
    }
}
