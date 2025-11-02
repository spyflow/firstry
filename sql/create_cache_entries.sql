-- Schema for Supabase-backed encrypted cache table used by SupabaseCache
-- Creates the default `cache_entries` table expected by the PHP helper.

create table if not exists public.cache_entries (
    key text primary key,
    data text not null,
    iv text not null,
    expires_at timestamptz,
    updated_at timestamptz not null default timezone('utc', now())
);

-- Optional index to make expiry pruning faster when running manual clean-ups.
create index if not exists cache_entries_expires_at_idx
    on public.cache_entries (expires_at);

-- Ensure the `updated_at` column is refreshed automatically on updates so that
-- merge operations coming from the API keep the timestamp in sync.
create or replace function public.set_cache_entries_updated_at()
returns trigger as $$
begin
    new.updated_at := timezone('utc', now());
    return new;
end;
$$ language plpgsql;

create or replace trigger set_cache_entries_updated_at
before update on public.cache_entries
for each row execute function public.set_cache_entries_updated_at();

-- Supabase enables RLS by default; allow authenticated clients to upsert and read.
-- When using the service role key, these policies are optional because the key
-- bypasses RLS, but they keep things working for anon keys too.
alter table public.cache_entries enable row level security;

do $$
begin
    if not exists (
        select 1 from pg_policies where schemaname = 'public'
          and tablename = 'cache_entries' and policyname = 'Allow cache read'
    ) then
        create policy "Allow cache read" on public.cache_entries
            for select using (true);
    end if;
    if not exists (
        select 1 from pg_policies where schemaname = 'public'
          and tablename = 'cache_entries' and policyname = 'Allow cache upsert'
    ) then
        create policy "Allow cache upsert" on public.cache_entries
            for all using (true) with check (true);
    end if;
end
$$;
