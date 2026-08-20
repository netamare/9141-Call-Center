import { MapContainer, TileLayer, CircleMarker, Popup } from "react-leaflet";
import type { EventRecord } from "@/lib/types";
import { PRIORITY_COLOR } from "@/components/badges";

export function HeatMapView({ events }: { events: EventRecord[] }) {
  return (
    <MapContainer
      center={[8.5414, 39.2689]}
      zoom={13}
      scrollWheelZoom
      className="h-full w-full rounded-xl"
    >
      <TileLayer
        attribution='&copy; OpenStreetMap contributors'
        url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
      />
      {events.map((e) => (
        <CircleMarker
          key={e.id}
          center={[e.lat, e.lng]}
          radius={10}
          pathOptions={{
            color: PRIORITY_COLOR[e.priority],
            fillColor: PRIORITY_COLOR[e.priority],
            fillOpacity: 0.5,
          }}
        >
          <Popup>
            <strong>{e.id}</strong>
            <br />
            {e.category} · {e.priority} · {e.status}
            <br />
            {e.location}
          </Popup>
        </CircleMarker>
      ))}
    </MapContainer>
  );
}
