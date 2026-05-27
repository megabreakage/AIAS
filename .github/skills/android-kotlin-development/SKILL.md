---
name: android-kotlin-development
description: "Build Android applications with Kotlin, Jetpack Compose, and MVI architecture. Use when creating Compose UI, ViewModels, Intents, States, repositories, Retrofit API clients, Room databases, navigation, offline-first patterns, push notifications, biometric auth, or real-time sync. Triggers: Android, Kotlin, Compose, MVI, mobile app."
---
# Android Kotlin Development

## When to Use
- Creating Android UI with Jetpack Compose
- Building MVI (Model-View-Intent) architecture
- Implementing API clients, Room databases, offline-first
- Push notifications, biometric authentication
- Real-time sync and telemetry interfaces

## Architecture: MVI

```
Intent → ViewModel → State → Compose UI
              ↕
         Repository
              ↕
     Remote (Retrofit) / Local (Room)
```

### Layer Rules
- **UI Layer**: Compose-only, observe State via `collectAsStateWithLifecycle()`
- **ViewModel**: Process Intents, emit States via `StateFlow`, side effects via `Channel`
- **Repository**: Single source of truth, coordinate remote + local
- **Data**: Retrofit for API, Room for cache, DataStore for preferences

## Package Structure

```
com.app.feature/
├── ui/
│   ├── FeatureScreen.kt
│   └── components/
├── domain/
│   ├── model/FeatureModel.kt
│   ├── usecase/GetFeatureUseCase.kt
│   └── repository/FeatureRepository.kt
├── data/
│   ├── remote/FeatureApi.kt
│   ├── local/FeatureDao.kt
│   └── mapper/FeatureMapper.kt
└── presentation/
    ├── FeatureViewModel.kt
    ├── FeatureIntent.kt
    └── FeatureState.kt
```

## MVI Pattern

```kotlin
// State
data class FeatureState(
    val isLoading: Boolean = false,
    val items: List<FeatureModel> = emptyList(),
    val error: String? = null
)

// Intent
sealed interface FeatureIntent {
    data object LoadItems : FeatureIntent
    data class DeleteItem(val id: String) : FeatureIntent
}

// ViewModel
class FeatureViewModel(
    private val repository: FeatureRepository
) : ViewModel() {
    private val _state = MutableStateFlow(FeatureState())
    val state: StateFlow<FeatureState> = _state.asStateFlow()

    fun processIntent(intent: FeatureIntent) {
        when (intent) {
            is FeatureIntent.LoadItems -> loadItems()
            is FeatureIntent.DeleteItem -> deleteItem(intent.id)
        }
    }
}
```

## Compose Best Practices
- Stateless composables — hoist state to ViewModel
- Use `remember`, `derivedStateOf` for computed values
- `LaunchedEffect` for side effects, `DisposableEffect` for cleanup
- Material 3 components + custom theme
- Preview with `@Preview` annotations

## Security
- Biometric: `BiometricPrompt` with `CryptoObject`
- Encrypted SharedPreferences for sensitive data
- Certificate pinning for API communication
- ProGuard/R8 for release builds
- No hardcoded secrets — use BuildConfig or encrypted storage

## Offline-First
- Room as local cache, Retrofit for remote
- Repository coordinates sync strategy
- WorkManager for background sync
- Conflict resolution: server wins (last-write-wins)
- Network state via `ConnectivityManager` callback

## Dependencies (Preferred)
- Compose BOM for version alignment
- Hilt for DI
- Retrofit + OkHttp for networking
- Room for local database
- Coil for image loading
- Navigation Compose for routing
