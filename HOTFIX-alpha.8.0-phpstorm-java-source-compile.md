# WorkTracker alpha.8.0 - PhpStorm Java source compile hotfix

## Fix

The PhpStorm plugin source accidentally used C# target-typed construction syntax inside Java:

```java
private static final ExecutionState IDLE = new("idle", null, null);
```

It is now valid Java:

```java
private static final ExecutionState IDLE = new ExecutionState("idle", null, null);
```

The build script also performs a fast source preflight for invalid `new(...)` Java syntax before Gradle spends time resolving/extracting the PhpStorm SDK.

No Laravel migration or Windows Agent change is required.
