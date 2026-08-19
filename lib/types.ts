export type UserRole = "ADMIN" | "OPERATOR" | "SUPERVISOR" | "DEPARTMENT_OFFICER";
export type EventStatus = "New" | "Assigned" | "Ongoing" | "Solved" | "Unsolved";
export type Priority = "Low" | "Medium" | "High" | "Critical";
export interface EventInput { 
    callerName:string;
     phoneNumber:string;
     gender?:string; 
     address?:string; 
     location:string; 
     category:string; 
     description:string; 
     priority:Priority; 
     department?:string; 
     status?:EventStatus; 
     remarks?:string 
    }