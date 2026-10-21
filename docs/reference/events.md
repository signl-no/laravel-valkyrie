# Events

All events live in `Signl\Valkyrie\Events`. They implement `ShouldDispatchAfterCommit`, so they fire only after the surrounding transaction commits, and they expose public readonly properties.

| Event | Properties | Dispatched when |
|---|---|---|
| `AbilityGranted` | `Model $actor`, `Ability $ability`, `?Model $scope` | `allow()` creates an allow row, or changes the `except` list on an existing one. |
| `AbilityForbidden` | `Model $actor`, `Ability $ability`, `?Model $scope` | `forbid()` creates a forbid row, or changes the `except` list on an existing one. |
| `AbilityRevoked` | `Model $actor`, `Ability $ability`, `?Model $scope`, `bool $wasForbidden` | `disallow()` removes an allow row (`wasForbidden: false`), or `unforbid()` removes a forbid row (`wasForbidden: true`). |
| `RoleAssigned` | `Model $actor`, `Role $role`, `?Model $scope` | `assign()` creates an assignment. |
| `RoleRetracted` | `Model $actor`, `Role $role`, `?Model $scope` | `retract()` removes an assignment. |

`$actor` is the model the write targeted, which can be a `Role` for grants made to a role. `$scope` is the effective scope, which for a scoped role definition is the definition's scope.

A repeated write that changes nothing dispatches no event. Writes made directly on the models, rather than through the facade or trait, dispatch no Valkyrie events. Neither does the cleanup that runs when an actor or an `IsAccessScope` model is deleted.
