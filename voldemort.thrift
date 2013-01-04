namespace php Accio

typedef i32 int
typedef i64 long

enum RequestType {
  GET = 0,
  GET_ALL = 1,
  PUT = 2,
  DELETE = 3,
  GET_VERSION = 4,
}

struct ClockEntry {
  1: required int node_id,
  2: required long version,
}

struct VectorClock {
  1: list<ClockEntry> entries,
  2: optional long timestamp,
}

struct Versioned {
  1: required binary value,
  2: required VectorClock version,
}

struct Error {
  1: required int error_code,
  2: required string error_message,
}

struct KeyedVersions {
  1: required binary key,
  2: list<Versioned> versions,
}

struct GetRequest {
  1: optional binary key,
  2: optional binary transforms,
}

struct GetResponse {
  1: list<Versioned> versioned,
  2: optional Error error,
}

struct GetVersionResponse {
  1: list<VectorClock> versions,
  2: optional Error error,
}

struct GetAllTransform {
  1: required binary key,
  2: required binary transform,
}

struct GetAllRequest {
  1: list<binary> keys,
  2: list<GetAllTransform> transforms,
}

struct GetAllResponse {
  1: list<KeyedVersions> values,
  2: optional Error error,
}

struct PutRequest {
  1: required binary key,
  2: required Versioned versioned,
  3: optional binary transforms,
}

struct PutResponse {
  1: optional Error error,
}

struct DeleteRequest {
  1: required binary key,
  2: required VectorClock version,
}

struct DeleteResponse {
  1: required bool success,
  2: optional Error error,
}

struct VoldemortRequest {
  1: required RequestType type,
  2: required bool should_route = false,
  3: required string store,
  4: optional GetRequest get,
  5: optional GetAllRequest getAll,
  6: optional PutRequest put,
  7: optional DeleteRequest delete2,
  8: optional int requestRoutetype,
}

