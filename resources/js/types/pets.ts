export interface PetAlert {
    kind: 'allergies' | 'chronic_conditions' | 'behavior_notes';
    label: string;
    text: string;
}

export interface PetSummary {
    id: string;
    name: string;
    species: string;
    breed: string | null;
    sex: string;
    sex_label: string;
    size: string;
    size_label: string;
    age: string | null;
    weight_kg: string | null;
    is_neutered: boolean;
    is_stray: boolean;
    alerts: PetAlert[];
}

export interface SpeciesOption {
    id: number;
    name: string;
    breeds: { id: number; name: string; default_size: string | null }[];
}
