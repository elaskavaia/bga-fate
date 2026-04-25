/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * Fate implementation : © Alena Laskavaia <laskava@gmail.com> - aka Victoria_La
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 */

export interface CustomPlayer extends Player {
  heroNo?: number;
}

export interface CustomGamedatas extends Gamedatas<CustomPlayer> {
  tokens: { [key: string]: Token };
  token_types: { [key: string]: any };
  counters: { [key: string]: { value: number } };
}

export interface NotificationMessage {
  log: string;
  args?: {
    [key: string]: any;
  };
}

export interface UiOptions {
  buttons?: boolean;
  replicate?: boolean;
  imagebuttons?: boolean;
  noactive?: boolean;
  undo?: boolean;
  color?: string; // buton color fallback
}
export interface ParamInfo extends UiOptions {
  q: number; // error code
  max?: number; // max count for this param

  err?: string | NotificationMessage; // error string if error code is set
  name?: string | NotificationMessage; // alternative param representation (can be rec tr)
  tooltip?: string | NotificationMessage;

  sec?: boolean; // this is secondary target
  o?: number; //  priority order
  color?: string; // button color
  confirm?: string; // extra confirmation dialog before submitting
  tokenIdUi?: string; // alternative token to display
  args?: any;

  info?: ParamInfoArray; // param info for next argument
}

export interface ParamInfoArray {
  [key: string]: ParamInfo;
}

export interface OpInfo {
  id: number;
  type: string; // operation type
  owner: string; // operation owner (color)
  data: any; // operation data

  ttype: string; // operation target type
  void: boolean; // operation is void
  target: string[]; // possible targets
  info: ParamInfoArray; // possible targets extra info

  confirm?: boolean; // require confirmation before sending to server
  description?: string; // for other players
  prompt?: string | NotificationMessage; // prompt when op is single/active
  subtitle?: string; // sub prompt when op is single/active (rended small subtext)

  err?: string | NotificationMessage; // error string or notification object XXX

  count?: number;
  mcount?: number;
  ui: UiOptions;
}
