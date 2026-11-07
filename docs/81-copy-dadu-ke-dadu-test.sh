#!/usr/bin/env bash
# Rescan81: source ec0ee1a -> Docker repo fdbd96e. No deploy, commit/push, or database commands.
set -euo pipefail
mode=${1:---dry-run}
case "$mode" in --dry-run|--copy) ;; *) printf 'Usage: bash %s [--dry-run|--copy]\n' "$0" >&2; exit 2 ;; esac
source_dir=${DADU_COPY_SOURCE:-/home/xdranel/Code/Project/php-laravel/dadu}
target_dir=${DADU_COPY_TARGET:-/home/xdranel/Code/Project/secret/dadu-test}
files=(
  app/Console/Commands/QueueExportMonthCommand.php
  app/Console/Commands/QueuePruneHistoryCommand.php
  app/Http/Controllers/Admin/QueueController.php
  app/Http/Controllers/Admin/QueueHistoryController.php
  app/Http/Controllers/Admin/SidangTaFormGuideController.php
  app/Http/Controllers/QueueController.php
  app/Http/Requests/Auth/LoginRequest.php
  app/Models/QueueState.php
  app/Models/QueueTicket.php
  app/Models/QueueTicketEvent.php
  app/Models/QueueTicketHistory.php
  app/Models/Student.php
  app/Models/User.php
  app/Services/QueueCounterService.php
  config/database.php
  config/queue.php
  config/services.php
  config/sidebar.php
  database/migrations/2026_10_27_000001_add_call_seq_to_queue_states_table.php
  database/migrations/2026_10_28_000001_add_skipped_status_to_queue_tickets_tables.php
  database/migrations/2026_10_28_000002_create_queue_ticket_events_table.php
  database/migrations/2026_10_28_000003_add_performance_indexes_to_queue_ticket_histories_table.php
  public/audio/queue/1.mp3
  public/audio/queue/10.mp3
  public/audio/queue/11.mp3
  public/audio/queue/2.mp3
  public/audio/queue/3.mp3
  public/audio/queue/4.mp3
  public/audio/queue/5.mp3
  public/audio/queue/6.mp3
  public/audio/queue/7.mp3
  public/audio/queue/8.mp3
  public/audio/queue/9.mp3
  public/audio/queue/Loket.mp3
  public/audio/queue/belas.mp3
  public/audio/queue/nomor_antrian.mp3
  public/audio/queue/puluh.mp3
  public/audio/queue/seratus.mp3
  public/audio/queue/silakan_menuju.mp3
  resources/views/components/sidebar.blade.php
  resources/views/queue-tickets/admin.blade.php
  resources/views/queue-tickets/display.blade.php
  resources/views/queue-tickets/history.blade.php
  resources/views/queue-tickets/status.blade.php
  resources/views/sidang_ta_registrations/partials/wizard-upload.blade.php
  resources/views/sidang_ta_registrations/wizard.blade.php
  resources/views/students/show.blade.php
  routes/console.php
  routes/web.php
  tests/Feature/Auth/AuthenticationTest.php
  tests/Feature/QueueAudioCallTest.php
  tests/Feature/QueueHistoryAndLogTest.php
  tests/Feature/QueueSkipAndDisplayTest.php
  tests/Unit/StudentSsoProfileTest.php
)
source_hashes=(
  828416a0b5995fe9a7d5f491c4405c8d645241eaa5c955e99202dbd7acb08aca
  08e3d9adb0c651b7ebaea2a932e13f151544d9d00bca3c12d0f8495103f1cc68
  34a1ef7dd215b5d96aee80de0b5de7f873e4074e38dee3ec4dd1b46864a460eb
  7811ec5a1aa18a1f16d826a9d9713d1f510dd6b36f4a765733ea1104a0a9dbed
  a81d4f3cea45b7fd6c3c455c39a3fa04fa88929c9fd88aab90dc2b02e03fa49b
  1e50f819dacdc5198083fb0f9029ca7073271eda2e3f7c1e7804a258b0cc9a0b
  339209415bec57f569bd57f28769eb0742a7a051e2c769bcbc14c561cc85f1e6
  4ab71ac80b010ab634a08bedb12b8c08a7e99a5f044a172c8140cb568909a7cc
  576abede0bb8d74066c71a497ea07352f9c8e24e5586359ec7d63f9656aaa746
  6f4afa85d36bf0567cebbb8d71904c3298d7f6b519f65126bfbf3bf128561dbc
  b50517e4518166c1305d34b5618ca116094e97a7adc4b8a8318962d89bf22fd4
  3730e1d3469693b567e5690016bfe0cb92e9e55b5a44f039825941d557e70f8a
  a2921356ddb622ebeea21f3706fb68813abc3837de25cf0fe8ab006b5c28c9bf
  6c4b12769f7fe887d2ac8c4099fb22319eae06aa5bed35cb62cd14548584e7b0
  f39a72319a8b2ee855f0329dc7be30e193c7d01189c83f177c3f792e73271618
  a388fb0fd8594f8ffbf3960f250c736adfbc2ec0350491b426722e45dda8a9f3
  e7662d0b8075be793c2a827f3fec9ea2b851a2123c7c2dc2fd7556e1960d661d
  476fdbe7edfe2124f2bf528ffdde31983ab1c12df2631216fa930a30ecd05f20
  91cdd9ff24a5afe4c45792d40aefba2f9a8130b61a177c3345690e0d8ce28bf5
  7faf8ca81df8b6bab82eee78969362ab465aaefa07194e15d440c2ca1221731e
  3bf7bd95dbdb6951c443051e5655d0709b1d51b2fb02f0080e3551e0ca683d48
  ec2a77265c563151a9721d18c8305fe34d06adb3ca9219f1555d8ecbed56f751
  3bdd37b0d858785915c385a6481a2d13e1856eb3bd7d9691f2806bb51243d501
  f92467b27cd28fea82b83b0af8dfd0f7a2f85146d93f836a984b4353d426d7e7
  cff120bd55d20a3b49be865ac73ca50f094d7ab51ed4391aa5c0ac03ed0dc6e7
  c38814cc5febab4bbe8e0d449c91e20c9a6c1b4b28318594ab5c01f04fdb330d
  746d268496e8e6a58a32a5de2f212cd1af371f411b83150e8b3ba13be816e5ef
  fe5c301390ef7e9e5bb22e10af37652abfabc5c9bddd00c85312f90b112d5d76
  5e56bb81b470673958b54972c206645aaac903fdcbf1470f57087e4e21277de2
  165946971c5a8d2b57ebcab87419d6bcaad0999c326ec1ca064ac942a17661ae
  09dcf87d10bd85ea99a0a94560923134deebcb580e5076bfea2bb6082fcfbc7f
  e8ee33ef6de3788be0c278df945e78113df72645e5ef0958d271a6772b27a286
  65f0bf53da9e0351f677007325f5c29521b863367677c0068cdb05d298cd55f1
  36ea1763f7ccb285ec630842df204b1ba9e7f32eca6e6029a0f0ba37c5bc4196
  49f0d33b14c8de60092436eb3307b1eb40b5270364c876061f8310edf753d2cc
  a4f8140e110cc8796e53443cb9ab088295f5dc5ee5ba2438b873b76deb45275b
  9de125dc95e8dfa5707a6626f77e3ae687146fc9e662b165e0308acd0d2a7e29
  9cd2fce52f1dd508c1c24a82fa05c1f0dd70b704aefc81698a519fba5416a5a0
  06ae89989160db22fa6dd561faa815e94259e4ac8bb84ce0f974be4a6675ef92
  5f2e141d32c25f4f760b8f50c1c3a321cb5cb1da93ff26da0de66d0638dbe43d
  db974ae0e6a342b990d79345f17f71fafda746925edbed46380aa7d213fca911
  9cdef9ae8a85835b31e99da0c2036cc1f36f649a2239fedb5491c1b616547509
  a89cba6ead1c717676e7e5572d492c4ba63cbd53f3bdfdda8f78eec1099a330a
  b4480bee35819ebcfbdfcea368bb040f3fe88f63289f0b7ca490f9d0bdefb4c4
  0b445500253c21713a85e89089b93c4a7c5b5f69c2659b028c4d54de2bee604d
  68219dd4e53f6587372b10c967e2e9d83dace52b85f1f395baadf893c042fad4
  8c24829571003e931a48eb0490e46515657336e530d598420d29eeac1c554797
  cdfc45cceef4a27fe77cd3d28bff8e933c8a170d9c30b505cf080ec11b154a17
  1e773190c7d3c50a396c7f6721b2d27657ee008a4fb044ed0026da38cee4774f
  f3889b65807a96c82c1f84476e07700b55b03dd0ff9e8cfc3807eb762b7b55e6
  0105799acb0d42375b282eec49a692048bf376a8917b076345c15eea19aa7f40
  5ee08a4291a3dac95f33db381b68879926e739335ee3ee78c130023c2e7910ee
  5b96f905e8ed2f4a6340d172b28fd2fb2c35989988664faa2c74e774be89dc01
  2134bfb5f6a121b45ba944a4d817f2450e1b50a4122131c6f59b3583ef4ff5e8
)
target_hashes=(
  MISSING
  MISSING
  a8f3cfcefe982b1b5075e8434d91a215b4ad28bdb10af9ec7e621760548f4a1a
  MISSING
  c21b2b254e1aad8c7cdb6d5e9908f3e6b11a693ab09bdd0dc77ae35bf028447a
  c5e71f40b94caa6f89bac210e2455bb88469724ba5566ec18dd3c0664dcdb8eb
  9754759ce8ed22c8875a02e83b8b9af42c86a68341b8a9aad4199d237b01a6f8
  71d76fde0ea0956576f997e65c652f2dc25abc409e3ef7702ccecee0d7f01e9e
  40ca1b7e71617748eae8452818ddf15daa43e8a8ce012e848886f326e2cc5a64
  MISSING
  12dafaaf5c2079b86945cac7e739e8a8b1f79fc750cf4efd404ccd734747ff64
  109efa278d2b3bbe1de6d3954462b54366cff2b692fbaf4b999ff54f85928e9a
  d31d90116bb4d36ccd1d35dc9a0054967b2df1daeee63caa3e35944b77cc1d30
  9449a1a115cd334813cde5fddcbcbdb787579cc33c38c49ade916582ff628152
  9a215fbdb06e2bbb10cb171ab23c84869173dc4a45774a1c91d4824bcca89358
  2aa1b75bc7496ebfafd3755114a99d46b099c14f2512a051b0d9b7902a8ddbfe
  7304f0d9423dc4d39a140fb03d09b81b68ef2bdb1f2ae0021d2b2d13955b167e
  1c818703ea8515defec86716906f112e7ced1e3a2286dd81eebb05677d51134c
  MISSING
  MISSING
  MISSING
  MISSING
  MISSING
  MISSING
  MISSING
  MISSING
  MISSING
  MISSING
  MISSING
  MISSING
  MISSING
  MISSING
  MISSING
  MISSING
  MISSING
  MISSING
  MISSING
  MISSING
  MISSING
  94b6dea45df1a9ce27d27d8421a2c767862b25fb0d9c5a051ad060b9963a956f
  3524d22cb63d02afa85f393c8f0b7e0dbdf313d724f60bb9bd3db42b4d909f2e
  1e4cd7f99d4f5de92e3b55a4de35a3a07d94ecddb2db30f771eefbbaed135c4c
  MISSING
  dc5bd3e15b2648751f7bbba358702fa3951f3137b2c80eaf44699833a5e8343c
  a4127bb8d18e3cfe058016eb85df75c064c6a13fcb11cf98c071e31a4673fcb0
  a7b8e8732fd1a1f8662de362d26292aa185e9d490f9a679008b165945b5da35d
  6857920d11da082bffe04257f5d9cc754cdc92437bc07c0fdf1204615a110d68
  bdf450916b51d01f12501f51b951d1d237055cf3f159452292545045220e2a7e
  7c798f57ba105b077bb333f605e84776ddc85cb8cd00fb29fd8d0df2fd40c6d1
  4756c66e1f25e4b2c3aeee4d9c1a52f1781650e7aa8f5d50ab9b989e758b4713
  MISSING
  MISSING
  MISSING
  MISSING
)
# Check the entire batch before copying any file.
for i in "${!files[@]}"; do
  relative_file=${files[$i]}
  source_file="$source_dir/$relative_file"
  target_file="$target_dir/$relative_file"
  if [[ ! -f "$source_file" ]]; then
    printf 'Source missing: %s\n' "$relative_file" >&2; exit 1
  fi
  source_digest=$(sha256sum -- "$source_file")
  source_digest=${source_digest%% *}
  if [[ "$source_digest" != "${source_hashes[$i]}" ]]; then
    printf 'Source changed since rescan81: %s. Rescan again.\n' "$relative_file" >&2; exit 1
  fi
  target_digest=MISSING
  if [[ -f "$target_file" ]]; then
    target_digest=$(sha256sum -- "$target_file")
    target_digest=${target_digest%% *}
  elif [[ -e "$target_file" || -L "$target_file" ]]; then
    printf 'Target is not a regular file: %s\n' "$relative_file" >&2; exit 1
  fi
  if [[ "$target_digest" != "${target_hashes[$i]}" && "$target_digest" != "$source_digest" ]]; then
    printf 'Target changed since rescan81: %s. Review before copying.\n' "$relative_file" >&2; exit 1
  fi
done
if [[ "$mode" == --dry-run ]]; then
  printf 'Ready to copy %s files:\n' "${#files[@]}"
  printf '  %s\n' "${files[@]}"
  exit 0
fi
backup_dir=$(mktemp -d "${TMPDIR:-/tmp}/dadu-copy81.XXXXXX")
for relative_file in "${files[@]}"; do
  if [[ -f "$target_dir/$relative_file" ]]; then
    install -D -m 0600 -- "$target_dir/$relative_file" "$backup_dir/$relative_file"
  fi
  install -D -m 0644 -- "$source_dir/$relative_file" "$target_dir/$relative_file"
  cmp -- "$source_dir/$relative_file" "$target_dir/$relative_file"
done
printf 'Copied and verified %s files. Backup: %s\n' "${#files[@]}" "$backup_dir"
