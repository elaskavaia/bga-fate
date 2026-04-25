# Effects DSL

This document describes the two domain-specific languages used in the Material CSVs to express card and ability behavior:

- **Op DSL** — used in the `r` field of card/event/operation CSVs. Compiles to a tree of operations queued onto the OpMachine. Parser: [OpExpression.php](../../modules/php/OpCommon/OpExpression.php).
- **Math DSL** — used in `pre` and similar fields, and inside Op DSL string-literal arguments (e.g. `'rank<=2'`). Evaluates to an integer. Parser: [MathExpression.php](../../modules/php/OpCommon/MathExpression.php).

The two DSLs share a lexer ([OpLexer / MathLexer](../../modules/php/OpCommon/OpExpression.php)) but have separate grammars. They are not interchangeable: the Op DSL describes *what to do*, the Math DSL describes *what to compute*.

---

## Op DSL

Op DSL strings compose operations using a small set of combinators. A parsed expression is a tree where leaves are operation names (or operation calls with arguments) and internal nodes are combinators with multiplicity.

### Combinators

Priority is the evaluation rank: **1 = binds tightest (evaluated first)**, higher numbers = looser binding (evaluated last). Unary prefix modifiers (`?`, `N`, `[from,to]`, `^`) are parsed before any binary combinator, so they bind tighter than every binary op — like unary minus in arithmetic.

| Op           | Priority | Meaning                                                                       | Arity        |
| ------------ | -------- | ----------------------------------------------------------------------------- | ------------ |
| `?`          | 1        | Optional — alias for `[0,1]`, the operand may be skipped                      | unary prefix |
| `N` / `[…]`  | 1        | Multiplicity prefix — bare integer or range bound on an operand               | unary prefix |
| `^`          | 1        | Limited-select modifier — pairs with a range, e.g. `[2,2]^a+b+c`              | prefix-mod   |
| `!`          | 2        | Atomic / terminal — internal marker, not normally written by hand             | n/a          |
| `,`          | 3        | Ordered AND, same priority — execute all in order                             | n-ary        |
| `+`          | 4        | Unordered AND — execute all, player picks order                               | n-ary        |
| `/`          | 5        | OR — player picks one branch                                                  | n-ary        |
| `:`          | 6        | Pay-to-get — left operand is the cost, right is the effect                    | binary       |
| `;`          | 7        | Ordered AND, different priority — like `,` but binds looser                   | n-ary        |
| `@`          | n/a      | No-op                                                                         | nullary      |

### Multiplicity

A leading integer or bracketed range bounds how many times an operand executes.

```
2x          execute x exactly twice
?x          execute x zero or one time (optional)
[1,3]x      execute x between 1 and 3 times
[2,]x       execute x at least 2 times (no upper bound)
[2,2]^a+b+c choose 2 distinct operands from {a,b,c}
```

Multiplicity binds tighter than any combinator.

### Atoms

A leaf is one of:

- **Identifier** — an op name made of letters, digits, `_` (e.g. `move`, `drawEvent`, `c_supfire`)
- **Number** — integer literal (e.g. `3`)
- **Quoted string** — single-quoted text passed verbatim as a parameter (e.g. `'rank<=2'`); typically a Math DSL expression evaluated at runtime
- **Op call** — `name(args)` where `args` is itself an Op DSL expression. The parser stores the inner text; the receiving operation interprets the arguments. Example: `addDamage(2)`, `killMonster(inRange,'rank<=2')`.

### Grammar (BNF)

```bnf
expression   ::= ranged ( binop expression )*
binop        ::= "," | ";" | "+" | "/" | ":"
ranged       ::= range? "^"? term
               | "?"     "^"? term
               | number  "^"? term
               | number                  ; bare number is also a terminal
range        ::= "[" number "," ( number | "" ) "]"
term         ::= "(" expression ")"
               | identifier "(" expression ")"   ; op call
               | identifier
               | number
               | quoted_string
identifier   ::= [a-zA-Z_] [a-zA-Z0-9_]*
quoted_string::= "'" .*? "'"
number       ::= "-"? [0-9]+
```

Whitespace between tokens is allowed and discarded. Adjacent terms with no combinator in between are joined by the parser's `defaultOp` (`,` by default — see `OpParser::parse($str, $defaultOp)`).

### Examples (from real cards)

```
move,attack                     ; move then attack
2drawEvent                      ; draw event card twice
?heal                           ; optionally heal
spendUse:heal(adj)              ; pay one use to heal an adjacent target
spendUse:(heal(adj)/repairCard) ; pay one use, then choose heal or repair
spendDurab:1preventDamage       ; pay 1 durability to prevent 1 damage
counter(countRunes):dealDamage(adj_attack)
                                ; cost = a counter expression; effect = damage
killMonster(inRange,'rank<=2 and closerToGrimheim')
                                ; op call with a math expression argument
[0,2]move(locationOnly),0setAtt(move)
                                ; up to 2 moves, then reset move attribute
(spendUse:1spendMana:gainAtt_move)
  /(spendUse:2spendMana:gainAtt_range)
  /(on(TActionAttack):2spendMana:2addDamage)
                                ; OR of three pay-to-get branches
```

### Round-tripping

`OpExpression::__toString()` regenerates a normalized string from the parse tree. Parentheses are inserted only where precedence demands them, so the output may differ syntactically from the input while remaining semantically equivalent.

`OpExpression::toArray()` and `toJson()` produce a structural dump in the form `[op, from, to, ...args]` for terminals and inner nodes alike.

---

## Math DSL

Math DSL strings evaluate to an integer. They are used wherever the engine needs a numeric or boolean condition (preconditions, counters, predicates inside Op DSL string-literal arguments). Booleans are represented as `0` / non-zero ints. Identifiers are resolved at evaluation time via a mapper callback supplied by the host (see `Base::evaluateExpression`).

### Operators

| Category    | Operators                                | Priority |
| ----------- | ---------------------------------------- | -------- |
| Arithmetic  | `+`  `-`  `*`  `/`  `%`                  | 1        |
| Comparison  | `<`  `<=`  `>`  `>=`  `==`               | 1        |
| Bitwise     | `&`  `\|`                                | 1        |
| Logical     | `&&`  `\|\|`  (also keywords `and` `or`) | 1        |
| Ternary     | `cond ? a : b`                           | 2        |

> ⚠ **All binary operators share priority 1** — the parser does **not** implement standard arithmetic precedence. Operators are applied strictly left-to-right (left-associative). So `a + b * c` evaluates as `(a + b) * c`, **not** `a + (b * c)`. **Always use parentheses** when mixing operators of different conceptual precedence.
>
> The ternary `?:` is the only construct with looser binding (priority 2), parsed after the surrounding binary chain.

### Functions

```
min(a, b, ...)      ; at least 2 args
max(a, b, ...)      ; at least 2 args
```

The function-call form is `name(arg1, arg2, ...)`. Other identifiers are looked up via the mapper.

### Atoms

- **Number** — integer literal
- **Identifier** — resolved by the host's mapper to an int (or another expression-evaluating-to-int)
- **Function call** — `name(arg, ...)` where each `arg` is itself a Math expression
- **Parenthesized expression** — `( expr )`

### Grammar (BNF)

```bnf
expression   ::= ternary
ternary      ::= binary ( "?" expression ":" expression )?
binary       ::= term ( binop term )*
binop        ::= "+" | "-" | "*" | "/" | "%"
               | "<" | "<=" | ">" | ">=" | "=="
               | "&" | "|" | "&&" | "||"
               | "and" | "or"
term         ::= "(" expression ")"
               | identifier "(" arglist? ")"
               | identifier
               | number
arglist      ::= expression ( "," expression )*
identifier   ::= [a-zA-Z_] [a-zA-Z0-9_]*
number       ::= "-"? [0-9]+
```

### Examples

```
rank<=2                              ; comparison
rank<=2 and closerToGrimheim         ; logical AND of two predicates
countRunes                           ; counter lookup
countRunes>0                         ; predicate
min(countRunes, 3)                   ; cap at 3
countActions==0 ? 2 : 1              ; ternary
```

These typically appear as quoted strings inside Op DSL op calls, e.g. `killMonster(inRange,'rank<=2 and closerToGrimheim')`. The Op parser passes the quoted body through verbatim; the consuming operation calls the Math parser on it.

---

## Cross-Reference

- Op DSL parse entry point: `OpExpression::parseExpression($str, $defaultOp = ",")` in [OpExpression.php](../../modules/php/OpCommon/OpExpression.php)
- Math DSL parse entry point: `MathExpression::parse($str)` in [MathExpression.php](../../modules/php/OpCommon/MathExpression.php)
- Math evaluation with mapper: `Base::evaluateExpression($cond, $owner, $context, $options)` in [Base.php](../../modules/php/Base.php)
