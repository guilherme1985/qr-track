<?php
declare(strict_types=1);

namespace ArkhamFiles;

use PDO;

/**
 * Model das categorias (árvore com depth ≤ 3).
 *
 * Convenções:
 *   - depth=0 → categoria root (parent_id = NULL)
 *   - depth=N → filha de uma categoria depth=N-1 (parent_id = id da pai)
 *   - depth=3 → folha máxima; não pode ter filhas
 *
 * Slug rules:
 *   - Auto-gerado a partir do nome se não fornecido
 *   - Unique por (parent_id, slug) — duas categorias podem ter o mesmo
 *     slug em ramos diferentes
 *   - Auto-rename ("cultivo", "cultivo-2", "cultivo-3"...) se colidir
 *
 * Delete:
 *   - Bloqueado por FK constraint (`ON DELETE RESTRICT`) se tiver filhas
 *   - Endpoint dá erro temático antes de tentar
 *   - Quando QRs existirem (PR 07+), também bloquearemos se tiver QRs
 *     atrelados (vai ter FK em qrcodes.category_id)
 */
final class Category
{
    public const MAX_DEPTH = 3;

    public function __construct(
        public readonly int $id,
        public readonly ?int $parentId,
        public readonly string $name,
        public readonly string $slug,
        public readonly ?string $icon,
        public readonly ?string $color,
        public readonly int $sortOrder,
        public readonly int $depth,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {}

    public function isRoot(): bool   { return $this->parentId === null; }
    public function isLeaf(): bool   { return $this->depth === self::MAX_DEPTH; }
    public function canHaveChildren(): bool { return $this->depth < self::MAX_DEPTH; }

    // ------------------------------------------------------------------
    // Lookups
    // ------------------------------------------------------------------

    public static function findById(int $id): ?self
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM categories WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? self::fromRow($row) : null;
    }

    public static function findBySlugPath(string $path): ?self
    {
        // Aceita "cultivo/indica/northern-lights" ou simples "cultivo"
        $segments = array_filter(explode('/', trim($path, '/')));
        if ($segments === []) {
            return null;
        }
        $parentId = null;
        $current = null;
        foreach ($segments as $slug) {
            $stmt = Database::pdo()->prepare(
                $parentId === null
                    ? 'SELECT * FROM categories WHERE parent_id IS NULL AND slug = :s'
                    : 'SELECT * FROM categories WHERE parent_id = :p AND slug = :s'
            );
            $params = [':s' => $slug];
            if ($parentId !== null) $params[':p'] = $parentId;
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) return null;
            $current = self::fromRow($row);
            $parentId = $current->id;
        }
        return $current;
    }

    /**
     * @return self[]  Filhas diretas, ordenadas por sort_order + name
     */
    public static function childrenOf(?int $parentId): array
    {
        $sql = $parentId === null
            ? 'SELECT * FROM categories WHERE parent_id IS NULL ORDER BY sort_order ASC, name COLLATE NOCASE ASC'
            : 'SELECT * FROM categories WHERE parent_id = :p ORDER BY sort_order ASC, name COLLATE NOCASE ASC';
        $stmt = Database::pdo()->prepare($sql);
        $params = $parentId === null ? [] : [':p' => $parentId];
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(self::fromRow(...), $rows);
    }

    /**
     * Lista todas as categorias planificadas, ordenadas pela árvore
     * (root primeiro, filhas indentadas). Útil para selects e listagens.
     *
     * @return list<array{category: self, depth: int, has_children: bool}>
     */
    public static function listFlat(): array
    {
        // Pega tudo e monta a estrutura em PHP (n pequeno; árvore depth≤3)
        $all = self::listAll();
        $byParent = [];  // parent_id => [Category, ...]
        foreach ($all as $c) {
            $key = $c->parentId ?? 0;
            $byParent[$key] ??= [];
            $byParent[$key][] = $c;
        }
        // Ordena cada bucket por sort_order + name
        foreach ($byParent as &$bucket) {
            usort($bucket, fn($a, $b) => $a->sortOrder <=> $b->sortOrder ?: strcasecmp($a->name, $b->name));
        }
        unset($bucket);

        $result = [];
        $visit = function (?int $parentId, int $depth) use (&$visit, &$result, &$byParent) {
            $key = $parentId ?? 0;
            foreach ($byParent[$key] ?? [] as $cat) {
                $result[] = [
                    'category'     => $cat,
                    'depth'        => $depth,
                    'has_children' => isset($byParent[$cat->id]) && $byParent[$cat->id] !== [],
                ];
                $visit($cat->id, $depth + 1);
            }
        };
        $visit(null, 0);
        return $result;
    }

    /** @return self[] */
    public static function listAll(): array
    {
        $stmt = Database::pdo()->query(
            'SELECT * FROM categories ORDER BY depth ASC, sort_order ASC, name COLLATE NOCASE ASC'
        );
        return array_map(self::fromRow(...), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Quantos filhos diretos a categoria tem.
     */
    public static function childCount(int $id): int
    {
        $stmt = Database::pdo()->prepare(
            'SELECT COUNT(*) FROM categories WHERE parent_id = :p'
        );
        $stmt->execute([':p' => $id]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Quantos QRs estão atrelados (PR 07+). Hoje sempre 0 porque CRUD
     * de QRs ainda não existe.
     */
    public static function qrCount(int $id): int
    {
        try {
            $stmt = Database::pdo()->prepare(
                'SELECT COUNT(*) FROM qrcodes WHERE category_id = :c'
            );
            $stmt->execute([':c' => $id]);
            return (int) $stmt->fetchColumn();
        } catch (\Throwable) {
            return 0; // Coluna category_id ainda não existe? Conta zero.
        }
    }

    // ------------------------------------------------------------------
    // Mutations
    // ------------------------------------------------------------------

    /**
     * Cria uma nova categoria. Calcula depth a partir do parent.
     * Auto-resolve colisão de slug ("cultivo" → "cultivo-2" → ...).
     *
     * @throws \DomainException se tentar criar filha de uma leaf (depth=3)
     */
    public static function create(
        string $name,
        ?int $parentId,
        ?string $requestedSlug = null,
        ?string $icon = null,
        ?string $color = null,
        int $sortOrder = 0,
    ): int {
        $name = trim($name);
        if ($name === '') {
            throw new \DomainException('Nome obrigatório');
        }

        $depth = 0;
        if ($parentId !== null) {
            $parent = self::findById($parentId);
            if ($parent === null) {
                throw new \DomainException('Categoria pai não encontrada');
            }
            if (!$parent->canHaveChildren()) {
                throw new \DomainException('Categoria pai já está na profundidade máxima');
            }
            $depth = $parent->depth + 1;
        }

        $slug = $requestedSlug !== null && trim($requestedSlug) !== ''
            ? self::slugify(trim($requestedSlug))
            : self::slugify($name);
        $slug = self::ensureUniqueSlug($slug, $parentId);

        $pdo = Database::pdo();
        $stmt = $pdo->prepare('
            INSERT INTO categories (parent_id, name, slug, icon, color, sort_order, depth)
            VALUES (:p, :n, :s, :i, :c, :o, :d)
        ');
        $stmt->execute([
            ':p' => $parentId,
            ':n' => $name,
            ':s' => $slug,
            ':i' => $icon !== null && $icon !== '' ? $icon : null,
            ':c' => $color !== null && $color !== '' ? $color : null,
            ':o' => $sortOrder,
            ':d' => $depth,
        ]);
        return (int) $pdo->lastInsertId();
    }

    /**
     * Atualiza name/slug/icon/color/sort_order. Não permite mudar parent_id
     * (move/reparent é uma operação complexa que muda depth de descendentes
     * inteiros — fora do escopo do PR 05).
     *
     * Slug ajustado se vier vazio (mantém atual) ou colide.
     */
    public static function update(
        int $id,
        string $name,
        ?string $requestedSlug,
        ?string $icon,
        ?string $color,
        int $sortOrder,
    ): void {
        $cat = self::findById($id);
        if ($cat === null) {
            throw new \DomainException('Categoria não encontrada');
        }
        $name = trim($name);
        if ($name === '') {
            throw new \DomainException('Nome obrigatório');
        }

        $slug = $cat->slug;
        if ($requestedSlug !== null && trim($requestedSlug) !== '') {
            $newSlug = self::slugify(trim($requestedSlug));
            if ($newSlug !== $cat->slug) {
                $slug = self::ensureUniqueSlug($newSlug, $cat->parentId, $id);
            }
        }

        Database::pdo()->prepare('
            UPDATE categories
               SET name = :n, slug = :s, icon = :i, color = :c,
                   sort_order = :o, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id
        ')->execute([
            ':n' => $name,
            ':s' => $slug,
            ':i' => $icon !== null && $icon !== '' ? $icon : null,
            ':c' => $color !== null && $color !== '' ? $color : null,
            ':o' => $sortOrder,
            ':id' => $id,
        ]);
    }

    /**
     * Tenta deletar. Falha (DomainException) se a categoria tem filhas.
     * QRs atrelados também bloqueiam quando essa relação existir (PR 07+).
     */
    public static function delete(int $id): void
    {
        $cat = self::findById($id);
        if ($cat === null) {
            throw new \DomainException('Categoria não encontrada');
        }
        if (self::childCount($id) > 0) {
            throw new \DomainException('Categoria com subcategorias não pode ser excluída');
        }
        if (self::qrCount($id) > 0) {
            throw new \DomainException('Categoria com arquivos atrelados não pode ser excluída');
        }
        Database::pdo()->prepare('DELETE FROM categories WHERE id = :id')
            ->execute([':id' => $id]);
    }

    // ------------------------------------------------------------------
    // Slug helpers
    // ------------------------------------------------------------------

    /**
     * Normaliza nome → slug url-safe.
     *   "Cultivo Indica"        → "cultivo-indica"
     *   "Notas & Memorandos"    → "notas-memorandos"
     *   "Ação/Reação"           → "acao-reacao"
     *   "  Espaços  Demais "    → "espacos-demais"
     */
    public static function slugify(string $input): string
    {
        $s = mb_strtolower(trim($input), 'UTF-8');
        // Converte acentos pra ASCII
        if (function_exists('iconv')) {
            $s = (string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        }
        $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
        $s = trim($s, '-');
        $s = (string) preg_replace('/-+/', '-', $s);
        return $s !== '' ? $s : 'categoria';
    }

    /**
     * Se o slug colide com outro no mesmo parent, adiciona sufixo numérico.
     * "$exceptId" permite excluir o próprio ID na busca (relevante em update).
     */
    private static function ensureUniqueSlug(string $base, ?int $parentId, ?int $exceptId = null): string
    {
        $slug = $base;
        $n = 1;
        while (self::slugExists($slug, $parentId, $exceptId)) {
            $n++;
            $slug = $base . '-' . $n;
            if ($n > 100) {
                throw new \RuntimeException('Não foi possível gerar slug único');
            }
        }
        return $slug;
    }

    private static function slugExists(string $slug, ?int $parentId, ?int $exceptId = null): bool
    {
        $sql = $parentId === null
            ? 'SELECT 1 FROM categories WHERE parent_id IS NULL AND slug = :s'
            : 'SELECT 1 FROM categories WHERE parent_id = :p AND slug = :s';
        $params = [':s' => $slug];
        if ($parentId !== null) $params[':p'] = $parentId;
        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $params[':id'] = $exceptId;
        }
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return (bool) $stmt->fetchColumn();
    }

    // ------------------------------------------------------------------
    // Hydration
    // ------------------------------------------------------------------

    /** @param array<string,mixed> $row */
    private static function fromRow(array $row): self
    {
        return new self(
            id:        (int) $row['id'],
            parentId:  isset($row['parent_id']) ? (int) $row['parent_id'] : null,
            name:      (string) $row['name'],
            slug:      (string) $row['slug'],
            icon:      isset($row['icon']) ? (string) $row['icon'] : null,
            color:     isset($row['color']) ? (string) $row['color'] : null,
            sortOrder: (int) ($row['sort_order'] ?? 0),
            depth:     (int) ($row['depth'] ?? 0),
            createdAt: (string) $row['created_at'],
            updatedAt: (string) ($row['updated_at'] ?? $row['created_at']),
        );
    }
}
